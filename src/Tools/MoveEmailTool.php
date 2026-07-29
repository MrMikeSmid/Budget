<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;
final class MoveEmailTool implements ToolInterface
{
    public function name(): string{return 'move_email';}
    public function definition(): array{return ['title'=>'Verplaats e-mail','description'=>'Verplaatst een e-mail via IMAP MOVE of een veilige COPY/DELETE/EXPUNGE-fallback.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>['string','integer']], 'destinationFolder'=>['type'=>'string'], 'folder'=>['type'=>'string'], 'account'=>['type'=>'string']],'required'=>['id','destinationFolder'],'additionalProperties'=>false]];}
    public function call(array $args): array
    {
        $client=null;
        try{$uid=MailMutationSupport::uid($args);$source=(string)($args['folder']??'INBOX');$destination=trim((string)($args['destinationFolder']??''));if($destination===''){throw new \InvalidArgumentException("'destinationFolder' is verplicht.");}$client=ImapClient::connect(Config::getAccount(isset($args['account'])?(string)$args['account']:null),$source);MailMutationSupport::requireMessage($client,$uid,$source);$client->move($uid,$destination);return Support::jsonResult(['success'=>true,'uid'=>$uid,'sourceFolder'=>$source,'destinationFolder'=>$destination]);}catch(ImapConnectionException $e){return Support::mailConnectionError($e);}catch(Throwable $e){return Support::errorResult('Kon e-mail niet verplaatsen: '.$e->getMessage());}finally{$client?->close();}
    }
}
