<?php
declare(strict_types=1); namespace McpEmail;
/** Small shared-hosting friendly fixed-window limiter; stores hashes, never tokens. */
final class RateLimiter {public static function allow(string $identity,int $maximum=60,int $window=60):bool{$dir=dirname(__DIR__).'/data/rate-limit';if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))return true;$slot=(int)floor(time()/$window);$path=$dir.'/'.hash('sha256',$identity.'|'.$slot).'.json';$fp=@fopen($path,'c+');if(!$fp)return true;try{if(!flock($fp,LOCK_EX))return true;$raw=stream_get_contents($fp);$count=is_string($raw)?(int)$raw:0;if($count>=$maximum)return false;ftruncate($fp,0);rewind($fp);fwrite($fp,(string)($count+1));return true;}finally{flock($fp,LOCK_UN);fclose($fp);}}}
