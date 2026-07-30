<?php

declare(strict_types=1);

namespace McpEmail\Security;

use McpEmail\Intelligence\AttachmentAnalyzer;
use McpEmail\Intelligence\LinkAnalyzer;

/** Deterministic, explainable risk estimator. It is not an antivirus guarantee. */
final class EmailSecurityAnalyzer
{
    /** @param array<string,mixed> $message @return array<string,mixed> */
    public function analyze(array $message): array
    {
        $headers=(new HeaderAnalyzer())->analyze((string)($message['headers']??''));
        $fromDomain=$this->domain((string)($headers['from']?:($message['from']??'')));
        $links=(new LinkAnalyzer())->analyze($message['html']??null,$message['text']??null,$fromDomain);
        $attachments=(new AttachmentAnalyzer())->analyze($message['attachments']??[]);
        $signals=[]; $score=0;
        $add=function(string $code,int $weight,string $reason)use(&$signals,&$score):void{$score+=$weight;$signals[]=['code'=>$code,'weight'=>$weight,'reason'=>$reason];};
        $weights=$this->weights();
        foreach(['spf','dkim','dmarc'] as $kind){$state=$headers[$kind];if(in_array($state,['fail','softfail'],true))$add(strtoupper($kind).'_FAIL',(int)($weights[$kind.'_fail']??($kind==='dmarc'?18:12)),strtoupper($kind)." rapporteert $state.");elseif($state==='missing')$add(strtoupper($kind).'_MISSING',4,strtoupper($kind).' ontbreekt; dit is een onzekerheid, geen bewijs van phishing.');}
        if($headers['address_mismatch']['reply_to'])$add('REPLY_TO_MISMATCH',(int)($weights['reply_to_mismatch']??15),'Reply-To gebruikt een ander domein dan From.');
        if($headers['address_mismatch']['return_path'])$add('RETURN_PATH_MISMATCH',8,'Return-Path gebruikt een ander domein dan From.');
        $corpus=mb_strtolower(strip_tags((string)($message['subject']??'').' '.(string)($message['text']??'').' '.(string)($message['html']??'')));
        $patterns=['URGENCY'=>['/\b(urgent|dringend|onmiddellijk|laatste (?:herinnering|waarschuwing))\b/u',6,'Urgentie- of laatste-herinneringstaal.'],
            'ACCOUNT_THREAT'=>['/\b(account|rekening).{0,30}(geblokkeerd|blocked|suspended|gesloten)\b/u',10,'Dreiging met accountblokkade.'],
            'CREDENTIAL_REQUEST'=>['/\b(wachtwoord|password|authenticatiecode|verification code|2fa code|inlogcode).{0,35}(stuur|deel|reply|provide|enter)\b/u',18,'Verzoek om wachtwoord of authenticatiecode.'],
            'PAYMENT_REQUEST'=>['/\b(betaal|payment|invoice|factuur|crypto|bitcoin|wallet|investering)\b/u',7,'Financieel, factuur-, crypto- of betaalverzoek.'],
            'LOGIN_NOTICE'=>['/\b(nieuwe|new|unexpected|onverwachte).{0,25}(login|inlog)\b/u',5,'(Onverwachte) loginmelding.'],
            'PERSONAL_DATA'=>['/\b(bsn|paspoort|passport|creditcard|persoonsgegevens)\b/u',10,'Verzoek of verwijzing naar gevoelige persoonsgegevens.']];
        foreach($patterns as $code=>[$regex,$weight,$reason])if(preg_match($regex,$corpus))$add($code,$weight,$reason);
        foreach($links as $link)if($link['risk']==='dangerous'){$add('DANGEROUS_LINK',20,'Link bevat meerdere technische risicosignalen: '.implode(', ',$link['risk_signals']).'.');break;}elseif($link['risk']==='suspicious'){$add('SUSPICIOUS_LINK',8,'Link bevat risicosignalen: '.implode(', ',$link['risk_signals']).'.');break;}
        foreach($attachments as $attachment)if($attachment['risk']==='dangerous'){$add('DANGEROUS_ATTACHMENT',(int)($weights['dangerous_attachment']??28),'Bijlage '.$attachment['filename'].' heeft een uitvoerbaar of misleidend bestandstype.');break;}elseif($attachment['risk']==='suspicious'){$add('SUSPICIOUS_ATTACHMENT',12,'Bijlage '.$attachment['filename'].' vereist extra controle.');break;}
        $score=min(100,$score);$level=$score<25?'low':($score<50?'attention':($score<75?'suspicious':'high'));
        $actions=['low'=>'Geen bijzondere actie; blijf alert op onverwachte verzoeken.','attention'=>'Controleer afzender en context via een bekend, onafhankelijk kanaal.','suspicious'=>'Klik niet en open geen bijlagen; verifieer via een onafhankelijk kanaal.','high'=>'Niet reageren, klikken of openen; isoleer en meld dit bericht aan de beheerder.'];
        return ['risk_score'=>$score,'risk_level'=>$level,'conclusion'=>match($level){'low'=>'Weinig lokale risicosignalen gevonden.','attention'=>'Enkele signalen vereisen aandacht.','suspicious'=>'De combinatie van signalen is verdacht.',default=>'Veel of zwaarwegende risicosignalen gevonden.'},
            'signals'=>$signals,'recommended_action'=>$actions[$level],'uncertainties'=>array_values(array_filter(['Geen externe reputatie- of malwarecontrole uitgevoerd.',in_array('missing',[$headers['spf'],$headers['dkim'],$headers['dmarc']],true)?'Een of meer authenticatieresultaten ontbreken.':null])),
            'authentication'=>$headers,'links'=>$links,'attachments'=>$attachments,'disclaimer'=>'Dit is een deterministische risico-inschatting en geen garantie dat een e-mail veilig of kwaadaardig is.'];
    }
    private function domain(string $value):string{return preg_match('/@[A-Z0-9.-]+/i',$value,$m)?strtolower(substr($m[0],1)):'';}
    private function weights():array{$path=dirname(__DIR__,2).'/config/security.php';$config=is_file($path)?require $path:[];return is_array($config['risk_weights']??null)?$config['risk_weights']:[];}
}
