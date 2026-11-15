' Chay chay-schedule.bat trong cua so an, dung cho Task Scheduler.
' wscript.exe la ung dung GUI nen khong tao cua so console; cmd ben trong chay an (window style 0).
Option Explicit
Dim sh, rc, bat
Set sh = CreateObject("WScript.Shell")
bat = "D:\Study\xampp\www\DA\scripts\windows\chay-schedule.bat"
rc = sh.Run("""" & bat & """", 0, True)
WScript.Quit rc
