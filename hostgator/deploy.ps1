# Deploy automatico Conecta 2.0 -> HostGator via UAPI cPanel
# Uso: .\deploy.ps1 -session "cpsessXXXXXX" [-files "index.html,style.css,app-bundle.js"]
#
# Onde pegar o session token:
#   Faca login no cPanel no browser e copie da URL.
#   Exemplo: https://sh00106.hostgator.com.br:2083/cpsess7632653565/frontend/...
#                                                   ^^^^^^^^^^^^^^^^
#                                                   esse eh o session

param(
  [Parameter(Mandatory=$true)][string]$session,
  [string]$files = "index.html,style.css,app-bundle.js,portal-taxas.php,portal-carteirinha.php"
)

Add-Type -AssemblyName System.Web

$host_cpanel = "sh00106.hostgator.com.br"
$dir         = "/home1/hg531e07/public_html/conecta"
$base_url    = "https://${host_cpanel}:2083/${session}/execute/Fileman/save_file_content"
$script_dir  = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "Deploy Conecta 2.0 -> $host_cpanel" -ForegroundColor Cyan
Write-Host "Diretorio remoto: $dir`n" -ForegroundColor DarkGray

$ok = 0; $fail = 0

foreach ($file in $files.Split(",")) {
  $file = $file.Trim()
  $path = Join-Path $script_dir $file

  if (-not (Test-Path $path)) {
    Write-Host "AVISO  $file - arquivo nao encontrado" -ForegroundColor Yellow
    $fail++
    continue
  }

  $content = Get-Content $path -Raw -Encoding UTF8
  $b64     = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($content))
  $body    = "dir=" + [System.Web.HttpUtility]::UrlEncode($dir) + "&file=" + [System.Web.HttpUtility]::UrlEncode($file) + "&content=" + [System.Web.HttpUtility]::UrlEncode($b64) + "&from_charset=utf-8&to_charset=utf-8"

  try {
    $result = Invoke-RestMethod -Uri $base_url -Method POST -Body $body -ContentType "application/x-www-form-urlencoded" -ErrorAction Stop
    if ($result.status -eq 1) {
      $size = [math]::Round($content.Length / 1024, 1)
      Write-Host "OK     $file ($size KB)" -ForegroundColor Green
      $ok++
    } else {
      $errs = if ($result.errors) { $result.errors -join '; ' } else { 'resposta sem status' }
      Write-Host "ERRO   $file - $errs" -ForegroundColor Red
      $fail++
    }
  } catch {
    Write-Host "ERRO   $file - $($_.Exception.Message)" -ForegroundColor Red
    $fail++
  }
}

Write-Host ""
Write-Host "Deploy concluido: $ok ok, $fail falhas" -ForegroundColor Cyan
if ($fail -gt 0) { exit 1 }
