$smartctl='C:\Program Files\smartmontools\bin\smartctl.exe'
$has_smart = Test-Path $smartctl
if($has_smart) {
    &$smartctl -a -j /dev/pd0 > out.json
    Get-Content out.json
}
