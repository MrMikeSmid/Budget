<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config; use McpEmail\Mail\ImapClient; use McpEmail\Mail\ImapConnectionException; use Throwable;
final class SecurityToolSupport
{
    public static function withMessage(array $args, callable $callback):array
    {
        $identifier=$args['uid']??$args['id']??0;$folder=self::folder($args['folder']??'INBOX');
        if(is_string($identifier)&&preg_match('/^(.+):(\d+)$/',$identifier,$match)){$folder=self::folder($match[1]);$identifier=$match[2];}
        $uid=(int)$identifier;
        if($uid<1)return Support::apiError('INVALID_ARGUMENT','UID moet een positief geheel getal zijn.');
        $client=null;try{$client=ImapClient::connect(Config::getAccount(isset($args['account'])?(string)$args['account']:null),$folder);$message=$client->readMessage($uid);
            if($message===null)return Support::apiError('EMAIL_NOT_FOUND','De gevraagde e-mail kon niet worden gevonden.');$message['folder']=$folder;return Support::apiSuccess($callback($message,$client));
        }catch(ImapConnectionException $e){return Support::mailConnectionError($e);}catch(Throwable){return Support::apiError('INTERNAL_ERROR','De aanvraag kon niet veilig worden verwerkt.');}finally{$client?->close();}
    }
    public static function folder(mixed $folder):string{$folder=trim((string)$folder);if($folder===''||strlen($folder)>255||preg_match('/[\x00-\x1f\x7f]/',$folder))throw new \InvalidArgumentException('Ongeldige foldernaam.');return $folder;}
    public static function schema():array{return ['uid'=>['type'=>'integer','minimum'=>1,'description'=>'IMAP UID van de e-mail.'],'id'=>['type'=>['string','integer'],'description'=>'Intern ID in de vorm folder:uid, of een UID.'],'folder'=>['type'=>'string','maxLength'=>255,'default'=>'INBOX'],'account'=>['type'=>'string','maxLength'=>100]];}
}
