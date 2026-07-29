<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Config;use McpEmail\Mail\ImapClient;use McpEmail\Mail\ImapConnectionException;use Throwable;
final class SearchEmailsTool implements ToolInterface
{
 public function name():string{return 'search_emails';}
 public function definition():array{return ['title'=>'Doorzoek mailbox','description'=>'Doorzoekt headers, onderwerp en waar ondersteund bodytekst via IMAP SEARCH. Resultaten zijn begrensd en gepagineerd; gebruik get_email voor volledige inhoud.','inputSchema'=>['type'=>'object','properties'=>[
  'query'=>['type'=>'string','maxLength'=>500],'folder'=>['type'=>'string','maxLength'=>255,'default'=>'INBOX'],'from'=>['type'=>'string','maxLength'=>320],'to'=>['type'=>'string','maxLength'=>320],'subject'=>['type'=>'string','maxLength'=>500],'body_contains'=>['type'=>'string','maxLength'=>500],
  'text'=>['type'=>'string','description'=>'Verouderd alias voor body_contains.'],'since'=>['type'=>'string','description'=>'Verouderd alias voor date_from.'],'before'=>['type'=>'string','description'=>'Verouderd alias voor date_to.'],
  'date_from'=>['type'=>'string','format'=>'date'],'date_to'=>['type'=>'string','format'=>'date'],'unread_only'=>['type'=>'boolean'],'has_attachments'=>['type'=>'boolean'],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100,'default'=>20],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000,'default'=>0],'sort'=>['type'=>'string','enum'=>['newest','oldest'],'default'=>'newest'],'account'=>['type'=>'string']],'additionalProperties'=>false]];}
 public function call(array $a):array{$folder=(string)($a['folder']??'INBOX');$limit=max(1,min(100,(int)($a['limit']??20)));$offset=max(0,(int)($a['offset']??0));$tokens=[];
  foreach(['FROM'=>'from','TO'=>'to','SUBJECT'=>'subject','BODY'=>'body_contains'] as $imap=>$arg)if(isset($a[$arg])&&$a[$arg]!=='')$tokens[]=$this->quoted($imap,(string)$a[$arg]);
  if(!empty($a['text']))$tokens[]=$this->quoted('BODY',(string)$a['text']);if(!empty($a['query']))$tokens[]=$this->quoted('TEXT',(string)$a['query']);
  foreach(['SINCE'=>($a['date_from']??$a['since']??null),'BEFORE'=>($a['date_to']??$a['before']??null)] as $k=>$v)if($v){try{$d=new \DateTimeImmutable((string)$v);if($k==='BEFORE'&&!isset($a['before']))$d=$d->modify('+1 day');$tokens[]="$k \"{$d->format('d-M-Y')}\"";}catch(Throwable){return Support::apiError('INVALID_ARGUMENT',"Ongeldige datum voor $k.");}}
  if(!empty($a['unread_only']))$tokens[]='UNSEEN';$criteria=$tokens?implode(' ',$tokens):'ALL';$c=null;
  try{$c=ImapClient::connect(Config::getAccount(isset($a['account'])?(string)$a['account']:null),$folder);$uids=$c->searchUids($criteria);if(($a['sort']??'newest')==='newest')rsort($uids);else sort($uids);$page=array_slice($uids,$offset,$limit);$rows=[];
   foreach($c->overviewsByUid($page) as $o){$s=Support::overviewToSummary($o);$uid=$s['id'];$message=null;if(array_key_exists('has_attachments',$a)||!empty($a['query'])||!empty($a['body_contains'])||!empty($a['text']))$message=$c->readMessage($uid);if(array_key_exists('has_attachments',$a)&&((bool)$a['has_attachments']!==(($message['attachments']??[])!==[])))continue;$headers=$message['headers']??$c->headers($uid);$snippet=trim(preg_replace('/\s+/u',' ',strip_tags((string)($message['text']??$message['html']??'')))??'');$rows[]=['id'=>$folder.':'.$uid,'uid'=>$uid,'message_id'=>Support::headerValue($headers,'Message-ID'),'folder'=>$folder,'from'=>$s['from'],'to'=>$s['to'],'subject'=>$s['subject'],'date'=>$s['date'],'unread'=>$s['unread'],'size'=>$s['size'],'attachment_count'=>count($message['attachments']??[]),'snippet'=>mb_substr($snippet,0,240)];}
   return Support::apiSuccess(['emails'=>$rows],['limit'=>$limit,'offset'=>$offset,'total'=>count($uids),'has_more'=>$offset+$limit<count($uids),'sort'=>$a['sort']??'newest']);
  }catch(ImapConnectionException $e){return Support::mailConnectionError($e);}catch(Throwable){return Support::apiError('SEARCH_FAILED','Zoeken naar e-mails is mislukt.');}finally{$c?->close();}}
 private function quoted(string $key,string $value):string{return $key.' "'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';}
}
