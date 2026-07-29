<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use Throwable;
/** Exposes attachment metadata risk analysis without downloading payloads. */
final class AnalyzeAttachmentsTool implements ToolInterface {
 public function name(): string { return 'analyze_attachments'; }
 public function definition(): array { return ['title'=>'Analyseer bijlagen','description'=>'Beoordeelt bestandsnaam, extensie, MIME-type en grootte zonder uitvoering.','inputSchema'=>IntelligenceSupport::schema(['id'=>['type'=>['integer','string'],'description'=>'E-mail UID.']],['id'])]; }
 public function call(array $args): array { try{$a=IntelligenceSupport::analyzeOne($args,false);return $a===null?Support::errorResult('E-mail niet gevonden.'):Support::jsonResult(['id'=>$a['id'],'attachments'=>$a['attachments']]);}catch(Throwable $e){return Support::errorResult('Bijlageanalyse mislukt: '.$e->getMessage());} }
}
