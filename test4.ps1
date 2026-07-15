$a = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument '/c "C:\Program Files\smartmontools\bin\smartctl.exe" -a -j /dev/sda > D:\xampp\htdocs\ras\sda.json'
$s = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -Action $a -Settings $s -TaskName 'TestSmart4' -User 'NT AUTHORITY\SYSTEM' | Out-Null
Start-ScheduledTask 'TestSmart4'
Start-Sleep -Seconds 3
Unregister-ScheduledTask 'TestSmart4' -Confirm:$false
