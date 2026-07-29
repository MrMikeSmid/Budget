<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config;
use McpEmail\Mail\ImapClient;
use McpEmail\Mail\ImapConnectionException;
use Throwable;
final class ArchiveEmailTool implements ToolInterface
{
    public function name(): string{return 'archive_email';}
    public function definition(): array{return ['title'=>'Archiveer e-mail','description'=>'Verplaatst een e-mail naar een opgegeven of automatisch gedetecteerde archiefmap.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>['string','integer']],'folder'=>['type'=>'string'],'archiveFolder'=>['type'=>'string'],'account'=>['type'=>'string']],'required'=>['id'],'additionalProperties'=>false]];}
    public function call(array $args): array{$client=null;try{$uid=MailMutationSupport::uid($args);$source=(string)($args['folder']??'INBOX');$client=ImapClient::connect(Config::getAccount(isset($args['account'])?(string)$args['account']:null),$source);MailMutationSupport::requireMessage($client,$uid,$source);$destination=isset($args['archiveFolder'])?trim((string)$args['archiveFolder']):$client->archiveFolder();if(!$destination){throw new \InvalidArgumentException('Geen archiefmap gevonden via SPECIAL-USE of providerstandaarden; geef archiveFolder op.');}$client->move($uid,$destination);return Support::jsonResult(['success'=>true,'uid'=>$uid,'sourceFolder'=>$source,'destinationFolder'=>$destination]);}catch(ImapConnectionException $e){return Support::mailConnectionError($e);}catch(Throwable $e){return Support::errorResult('Kon e-mail niet archiveren: '.$e->getMessage());}finally{$client?->close();}}
}
