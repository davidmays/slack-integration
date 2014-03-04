<?
function log($logfile, $payload)
{
    $fh = fopen($logfile,"ra+");
    fwrite($fh,time()."\n");
    fwrite($fh,$payload);
    fwrite($fh,"\n____________________\n");
    
    fclose($fh);
}
?>
