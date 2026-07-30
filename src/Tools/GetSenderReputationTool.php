<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use McpEmail\Intelligence\ReputationStore;
use Throwable;
/** Reads accumulated local reputation for a sender domain. */
final class GetSenderReputationTool implements ToolInterface {
 public function name(): string { return 'get_sender_reputation'; }
 public function definition(): array { return ['title'=>'Afzenderreputatie','description'=>'Geeft lokaal opgebouwde, transparante reputatiestatistieken per domein.','inputSchema'=>['type'=>'object','properties'=>['domain'=>['type'=>'string','description'=>'Afzenderdomein.']],'required'=>['domain'],'additionalProperties'=>false]]; }
 public function call(array $args): array { try{$domain=strtolower(trim((string)($args['domain']??'')));if($domain===''||str_contains($domain,'@'))return Support::errorResult('Geef een geldig domein zonder @ op.');return Support::jsonResult((new ReputationStore())->get($domain));}catch(Throwable $e){return Support::errorResult('Reputatie ophalen mislukt: '.$e->getMessage());} }
}
