<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;
final class DeleteEmailTool implements ToolInterface
{
    public function name(): string { return 'delete_email'; }
    public function definition(): array { return ['title'=>'Verwijder e-mail','description'=>'Markeert een e-mail via UID als verwijderd en voert alleen bij permanent=true EXPUNGE uit.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>['string','integer']], 'folder'=>['type'=>'string'], 'account'=>['type'=>'string'], 'permanent'=>['type'=>'boolean','default'=>false]],'required'=>['id'],'additionalProperties'=>false]]; }
    public function call(array $args): array
    {
        $client=null;
        try {
            $uid=MailMutationSupport::uid($args); $folder=(string)($args['folder']??'INBOX'); $permanent=(bool)($args['permanent']??false);
            $client=ImapClient::connect(Config::getAccount(isset($args['account'])?(string)$args['account']:null),$folder);
            MailMutationSupport::requireMessage($client,$uid,$folder); $client->setFlag($uid,'\\Deleted',true); if($permanent){$client->expunge();}
            return Support::jsonResult(['success'=>true,'uid'=>$uid,'folder'=>$folder,'permanent'=>$permanent]);
        } catch(ImapConnectionException $e){return Support::mailConnectionError($e);} catch(Throwable $e){return Support::errorResult('Kon e-mail niet verwijderen: '.$e->getMessage());} finally{$client?->close();}
    }
}
