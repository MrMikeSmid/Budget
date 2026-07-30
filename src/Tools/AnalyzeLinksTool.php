<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use Throwable;
/** Exposes the URL portion of an email analysis. */
final class AnalyzeLinksTool implements ToolInterface {
 public function name(): string { return 'analyze_links'; }
 public function definition(): array { return ['title'=>'Analyseer links','description'=>'Extraheert en beoordeelt hyperlinks zonder ze te bezoeken.','inputSchema'=>IntelligenceSupport::schema(['id'=>['type'=>['integer','string'],'description'=>'E-mail UID.']],['id'])]; }
 public function call(array $args): array { try{$a=IntelligenceSupport::analyzeOne($args,false);return $a===null?Support::errorResult('E-mail niet gevonden.'):Support::jsonResult(['id'=>$a['id'],'links'=>$a['links']]);}catch(Throwable $e){return Support::errorResult('Linkanalyse mislukt: '.$e->getMessage());} }
}
