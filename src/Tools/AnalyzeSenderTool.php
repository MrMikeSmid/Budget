<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Intelligence\ReputationStore;
use Throwable;
/** Summarizes locally learned observations about a sender domain. */
final class AnalyzeSenderTool implements ToolInterface {
 public function name(): string { return 'analyze_sender'; }
 public function definition(): array { return ['title'=>'Analyseer afzender','description'=>'Analyseert de lokaal opgebouwde historie en risicobalans van een afzenderdomein.','inputSchema'=>['type'=>'object','properties'=>['domain'=>['type'=>'string','description'=>'Afzenderdomein.']],'required'=>['domain'],'additionalProperties'=>false]]; }
 public function call(array $args): array { try{$domain=strtolower(trim((string)($args['domain']??'')));if($domain===''||str_contains($domain,'@'))return Support::errorResult('Geef een geldig domein zonder @ op.');$r=(new ReputationStore())->get($domain);$t=$r['totals'];$bad=$t['spam_count']+$t['phishing_count'];$seen=max(1,$t['times_seen']);$r['risk']=$r['known']?($bad/$seen>.25?'suspicious':'known'):'unknown';$r['recommendation']=$r['risk']==='suspicious'?'verify_sender':'read_normally';return Support::jsonResult($r);}catch(Throwable $e){return Support::errorResult('Afzenderanalyse mislukt: '.$e->getMessage());} }
}
