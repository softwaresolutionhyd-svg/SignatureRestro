$pdf = 'C:\Users\Usman Computers\Downloads\DOC-20260704-WA0000..pdf'
$out = 'C:\laragon\www\signature\menu_extract.docx'

$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0

try {
    $confirmConversions = $false
    $readOnly = $true
    $addToRecentFiles = $false
    $passwordDocument = ''
    $passwordTemplate = ''
    $revert = $false
    $writePasswordDocument = ''
    $writePasswordTemplate = ''
    $format = 0
    $encoding = 0
    $visible = $false
    $openAndRepair = $false
    $documentDirection = 0
    $noEncodingDialog = $true
    $xmlTransform = ''

    $doc = $word.Documents.Open(
        $pdf,
        [ref]$confirmConversions,
        [ref]$readOnly,
        [ref]$addToRecentFiles,
        [ref]$passwordDocument,
        [ref]$passwordTemplate,
        [ref]$revert,
        [ref]$writePasswordDocument,
        [ref]$writePasswordTemplate,
        [ref]$format,
        [ref]$encoding,
        [ref]$visible,
        [ref]$openAndRepair,
        [ref]$documentDirection,
        [ref]$noEncodingDialog,
        [ref]$xmlTransform
    )

    $wdFormatDocumentDefault = 16
    $doc.SaveAs2([ref]$out, [ref]$wdFormatDocumentDefault)
    $doc.Close()
    Write-Output $out
}
finally {
    $word.Quit()
}
