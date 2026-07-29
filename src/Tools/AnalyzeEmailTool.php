<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use Throwable;
/** Produces a complete, read-only security and content analysis for one message. */
final class AnalyzeEmailTool implements ToolInterface {
 public function name(): string { return 'analyze_email'; }
 public function definition(): array { return ['title'=>'Analyseer e-mail','description'=>'Classificeert één e-mail lokaal, transparant en zonder de mailbox te wijzigen.','inputSchema'=>IntelligenceSupport::schema(['id'=>['type'=>['integer','string'],'description'=>'E-mail UID.']], ['id'])]; }
 public function call(array $args): array { try { $value=IntelligenceSupport::analyzeOne($args); return $value===null?Support::errorResult('E-mail niet gevonden.'):Support::jsonResult($value); } catch(Throwable $e){ return Support::errorResult('E-mailanalyse mislukt: '.$e->getMessage()); } }
}
