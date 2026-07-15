$d = [PSCustomObject]@{DeviceId=0}
$smartctl = 'C:\Program Files\smartmontools\bin\smartctl.exe'
$sdname = '/dev/pd' + $d.DeviceId
$sout = &$smartctl -a -j $sdname | ConvertFrom-Json
$sout | ConvertTo-Json -Depth 5 > out2.json
