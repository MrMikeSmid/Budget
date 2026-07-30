<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use McpEmail\Mail\SmtpClient;
use Throwable;
final class ReplyEmailTool implements ToolInterface
{
    public function name(): string{return 'reply_email';}
    public function definition(): array{return ['title'=>'Beantwoord e-mail','description'=>'Leest een e-mail via IMAP en verstuurt een reply met correcte threadheaders via SMTP.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>['string','integer']],'body'=>['type'=>'string'],'html'=>['type'=>'string'],'replyAll'=>['type'=>'boolean','default'=>false],'folder'=>['type'=>'string'],'account'=>['type'=>'string']],'required'=>['id','body'],'additionalProperties'=>false]];}
    public function call(array $args): array{$client=null;try{$uid=MailMutationSupport::uid($args);if(!array_key_exists('body',$args)){throw new \InvalidArgumentException("'body' is verplicht.");}$folder=(string)($args['folder']??'INBOX');$account=Config::getAccount(isset($args['account'])?(string)$args['account']:null);$client=ImapClient::connect($account,$folder);$message=MailMutationSupport::requireMessage($client,$uid,$folder);$from=Support::addresses((string)$message['from']);$recipients=$from;if((bool)($args['replyAll']??false)){$recipients=array_merge($recipients,Support::addresses((string)$message['to']),Support::headerAddresses((string)$message['headers'],'Cc'));}$own=strtolower($account->fromAddress);$recipients=array_values(array_unique(array_filter($recipients,static fn(string $address):bool=>strtolower($address)!==$own)));if($recipients===[]){throw new \InvalidArgumentException('Geen geldige reply-ontvanger gevonden.');}$messageId=Support::headerValue((string)$message['headers'],'Message-ID');$references=trim(Support::headerValue((string)$message['headers'],'References').' '.$messageId);$subject=preg_match('/^\s*re\s*:/i',(string)$message['subject'])?(string)$message['subject']:'Re: '.$message['subject'];$newId=SmtpClient::send($account,$recipients,$subject,(string)$args['body'],isset($args['html'])?(string)$args['html']:null,null,null,array_filter(['In-Reply-To'=>$messageId,'References'=>$references]));$client->setFlag($uid,'\\Answered',true);return Support::jsonResult(['success'=>true,'messageId'=>$newId,'recipients'=>$recipients]);}catch(ImapConnectionException $e){return Support::mailConnectionError($e);}catch(Throwable $e){return Support::errorResult('Kon e-mail niet beantwoorden: '.$e->getMessage());}finally{$client?->close();}}
}
