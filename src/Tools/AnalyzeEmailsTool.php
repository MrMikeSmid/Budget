<?php
declare(strict_types=1);
namespace McpEmail\Tools;
use Throwable;
/** Batch-analyzes recent messages using one IMAP connection. */
final class AnalyzeEmailsTool implements ToolInterface {
 public function name(): string { return 'analyze_emails'; }
 public function definition(): array { return ['title'=>'Analyseer recente e-mails','description'=>'Analyseert maximaal 50 recente e-mails zonder mailboxwijzigingen.','inputSchema'=>IntelligenceSupport::schema(['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>50,'description'=>'Aantal berichten, standaard 20.']])]; }
 public function call(array $args): array { try { $values=IntelligenceSupport::analyzeMany($args); return Support::jsonResult(['count'=>count($values),'emails'=>$values]); } catch(Throwable $e){ return Support::errorResult('Batchanalyse mislukt: '.$e->getMessage()); } }
}
