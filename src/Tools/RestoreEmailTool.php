<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;
final class RestoreEmailTool implements ToolInterface
{
    public function name(): string{return 'restore_email';}
    public function definition(): array{return ['title'=>'Herstel e-mail','description'=>'Verplaatst een e-mail vanuit Archive of Trash naar een doelmap.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>['string','integer']],'sourceFolder'=>['type'=>'string'],'destinationFolder'=>['type'=>'string'],'account'=>['type'=>'string']],'required'=>['id','sourceFolder','destinationFolder'],'additionalProperties'=>false]];}
    public function call(array $args): array{$client=null;try{$uid=MailMutationSupport::uid($args);$source=trim((string)($args['sourceFolder']??''));$destination=trim((string)($args['destinationFolder']??''));if($source===''||$destination===''){throw new \InvalidArgumentException('sourceFolder en destinationFolder zijn verplicht.');}$client=ImapClient::connect(Config::getAccount(isset($args['account'])?(string)$args['account']:null),$source);MailMutationSupport::requireMessage($client,$uid,$source);$client->move($uid,$destination);return Support::jsonResult(['success'=>true,'uid'=>$uid,'sourceFolder'=>$source,'destinationFolder'=>$destination]);}catch(ImapConnectionException $e){return Support::mailConnectionError($e);}catch(Throwable $e){return Support::errorResult('Kon e-mail niet herstellen: '.$e->getMessage());}finally{$client?->close();}}
}
