[CmdletBinding()]
param(
    [string] $ApiUrl = 'http://127.0.0.1:8080',
    [string] $EnvFile = '',
    [switch] $Gmail
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

if ([string]::IsNullOrWhiteSpace($EnvFile)) {
    $scriptDirectory = Split-Path -Parent $PSCommandPath
    $EnvFile = Join-Path (Split-Path -Parent $scriptDirectory) '.env'
}

function Read-RequiredValue {
    param([string] $Prompt, [string] $Default = '')
    while ($true) {
        $label = if ($Default -ne '') { "$Prompt [$Default]" } else { $Prompt }
        $value = Read-Host $label
        if ([string]::IsNullOrWhiteSpace($value)) { $value = $Default }
        if (-not [string]::IsNullOrWhiteSpace($value)) { return $value.Trim() }
        Write-Warning 'A value is required.'
    }
}

function Read-Port {
    while ($true) {
        $raw = Read-RequiredValue -Prompt 'IMAP port' -Default '993'
        $port = 0
        if ([int]::TryParse($raw, [ref] $port) -and $port -ge 1 -and $port -le 65535) { return $port }
        Write-Warning 'Enter a port between 1 and 65535.'
    }
}

function Read-Encryption {
    while ($true) {
        $value = (Read-RequiredValue -Prompt 'Encryption (ssl, tls, or starttls)' -Default 'ssl').ToLowerInvariant()
        if ($value -in @('ssl', 'tls', 'starttls')) { return $value }
        Write-Warning 'Encryption must be ssl, tls, or starttls.'
    }
}

function Read-YesNo {
    param([string] $Prompt, [bool] $Default)
    $suffix = if ($Default) { 'Y/n' } else { 'y/N' }
    while ($true) {
        $answer = (Read-Host "$Prompt [$suffix]").Trim().ToLowerInvariant()
        if ($answer -eq '') { return $Default }
        if ($answer -in @('y', 'yes')) { return $true }
        if ($answer -in @('n', 'no')) { return $false }
        Write-Warning 'Enter y or n.'
    }
}

function ConvertFrom-HiddenInput {
    param([SecureString] $SecureValue, [string] $Name)
    $pointer = [IntPtr]::Zero
    try {
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
        $value = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
        if ([string]::IsNullOrWhiteSpace($value)) { throw "$Name is required." }
        return $value
    }
    finally {
        if ($pointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    }
}

function Read-ApiToken {
    if (-not [string]::IsNullOrWhiteSpace($env:THREADMESH_API_TOKEN)) { return $env:THREADMESH_API_TOKEN }
    if (Test-Path -LiteralPath $EnvFile) {
        $tokenLine = Get-Content -LiteralPath $EnvFile |
            Where-Object { $_ -match '^THREADMESH_API_TOKEN=' } |
            Select-Object -First 1
        if ($tokenLine) {
            $token = ($tokenLine -split '=', 2)[1].Trim()
            if ($token -ne '') { return $token }
        }
    }
    return ConvertFrom-HiddenInput `
        -SecureValue (Read-Host 'THREADMESH_API_TOKEN (input is hidden)' -AsSecureString) `
        -Name 'THREADMESH_API_TOKEN'
}

function Invoke-ThreadMesh {
    param(
        [ValidateSet('Get', 'Post')][string] $Method,
        [string] $Path,
        [object] $Body = $null
    )
    $parameters = @{ Method = $Method; Uri = $script:BaseUrl + $Path; Headers = $script:Headers }
    if ($null -ne $Body) {
        $parameters.ContentType = 'application/json'
        $parameters.Body = ($Body | ConvertTo-Json -Depth 8)
    }
    try { return Invoke-RestMethod @parameters }
    catch {
        $message = $_.Exception.Message
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            try {
                $errorResponse = $_.ErrorDetails.Message | ConvertFrom-Json
                if ($errorResponse.error) { $message = [string] $errorResponse.error }
            }
            catch { }
        }
        throw "ThreadMesh API request failed: $message"
    }
}

function Show-Folders {
    param([object[]] $Folders)
    Write-Host ''
    Write-Host 'Available IMAP folders:'
    for ($index = 0; $index -lt $Folders.Count; $index++) {
        Write-Host ('  {0,2}. {1}  [{2}]' -f ($index + 1), $Folders[$index].displayName, $Folders[$index].id)
    }
}

function Read-FolderIndex {
    param([string] $Prompt, [object[]] $Folders, [int] $DefaultIndex = 0)
    while ($true) {
        $default = if ($DefaultIndex -gt 0) { [string] $DefaultIndex } else { '' }
        $raw = Read-RequiredValue -Prompt $Prompt -Default $default
        $selected = 0
        if ([int]::TryParse($raw, [ref] $selected) -and $selected -ge 1 -and $selected -le $Folders.Count) {
            return $selected - 1
        }
        Write-Warning "Enter a folder number between 1 and $($Folders.Count)."
    }
}

function Read-SyncFolderIndexes {
    param([object[]] $Folders, [int] $DefaultIndex = 0)
    while ($true) {
        $default = if ($DefaultIndex -gt 0) { [string] $DefaultIndex } else { '' }
        $raw = Read-RequiredValue -Prompt 'Folders to synchronize (comma-separated numbers)' -Default $default
        $indexes = [System.Collections.Generic.List[int]]::new()
        $valid = $true
        foreach ($part in ($raw -split ',')) {
            $selected = 0
            if (-not [int]::TryParse($part.Trim(), [ref] $selected) -or $selected -lt 1 -or $selected -gt $Folders.Count) {
                $valid = $false
                break
            }
            $zeroBased = $selected - 1
            if (-not $indexes.Contains($zeroBased)) { $indexes.Add($zeroBased) }
        }
        if ($valid -and $indexes.Count -gt 0) { return $indexes.ToArray() }
        Write-Warning "Enter one or more folder numbers between 1 and $($Folders.Count), separated by commas."
    }
}

$script:BaseUrl = $ApiUrl.TrimEnd('/')
$script:Headers = @{ Authorization = 'Bearer ' + (Read-ApiToken) }
$plainPassword = $null
$accountPayload = $null

try {
    Write-Host 'ThreadMesh IMAP account setup'
    Write-Host 'The password is read locally with hidden input and is sent only to the configured ThreadMesh API.'
    if ($Gmail) {
        Write-Host 'Gmail preset: imap.gmail.com:993 over SSL; only INBOX will be initialized.'
        Write-Host 'Use a Google app password, not the main Google Account password.'
    }
    Write-Host ''
    $accountIdDefault = if ($Gmail) { 'gmail' } else { '' }
    $accountId = Read-RequiredValue `
        -Prompt 'Account ID (letters, numbers, dot, underscore, or hyphen)' `
        -Default $accountIdDefault
    if ($accountId -notmatch '^[A-Za-z0-9][A-Za-z0-9._-]*$') {
        throw 'Account ID must start with a letter or number and contain only letters, numbers, dot, underscore, or hyphen.'
    }
    $displayNameDefault = if ($Gmail) { 'Gmail' } else { $accountId }
    $displayName = Read-RequiredValue -Prompt 'Display name' -Default $displayNameDefault
    if ($Gmail) {
        $hostName = 'imap.gmail.com'
        $port = 993
        $encryption = 'ssl'
        $validateCertificate = $true
        $username = Read-RequiredValue -Prompt 'Gmail address'
    }
    else {
        $hostName = Read-RequiredValue -Prompt 'IMAP server'
        $port = Read-Port
        $encryption = Read-Encryption
        $validateCertificate = Read-YesNo -Prompt 'Validate the TLS certificate' -Default $true
        $username = Read-RequiredValue -Prompt 'IMAP username'
    }
    $secretPrompt = if ($Gmail) { 'Google app password (input is hidden)' } else { 'IMAP password or app password (input is hidden)' }
    $secretName = if ($Gmail) { 'Google app password' } else { 'IMAP password' }
    $plainPassword = ConvertFrom-HiddenInput `
        -SecureValue (Read-Host $secretPrompt -AsSecureString) `
        -Name $secretName

    $configuration = @{
        host = $hostName
        port = $port
        encryption = $encryption
        validateCertificate = $validateCertificate
        username = $username
        draftFolder = 'Drafts'
    }
    $accountPayload = @{
        id = $accountId
        displayName = $displayName
        secret = $plainPassword
        enabled = $true
        configuration = $configuration
    }
    $null = Invoke-ThreadMesh -Method Post -Path '/v1/accounts' -Body $accountPayload
    $accountPayload = $null
    $plainPassword = $null

    $encodedAccountId = [Uri]::EscapeDataString($accountId)
    $test = Invoke-ThreadMesh -Method Post -Path "/v1/accounts/$encodedAccountId/test" -Body @{}
    if (-not $test.succeeded) {
        throw "IMAP connection test failed: $($test.message). Re-run this script with the same account ID to update its settings."
    }
    Write-Host "Connection test succeeded: $($test.message)" -ForegroundColor Green

    $folderResponse = Invoke-ThreadMesh -Method Get -Path "/v1/accounts/$encodedAccountId/folders"
    $folders = @($folderResponse.folders)
    if ($folders.Count -eq 0) { throw 'The IMAP server returned no folders.' }
    Show-Folders -Folders $folders

    $draftDefault = 0
    $inboxDefault = 0
    for ($index = 0; $index -lt $folders.Count; $index++) {
        if ($folders[$index].id -match '(^|[/\\])Drafts$' -or $folders[$index].displayName -match '(^|[/\\])Drafts$') { $draftDefault = $index + 1 }
        if ($folders[$index].id -ieq 'INBOX') { $inboxDefault = $index + 1 }
    }
    $draftIndex = Read-FolderIndex -Prompt 'Folder used for reply drafts' -Folders $folders -DefaultIndex $draftDefault
    $configuration.draftFolder = [string] $folders[$draftIndex].id
    $null = Invoke-ThreadMesh -Method Post -Path '/v1/accounts' -Body @{
        id = $accountId; displayName = $displayName; enabled = $true; configuration = $configuration
    }

    if ($Gmail) {
        if ($inboxDefault -eq 0) { throw 'Gmail did not return the required INBOX folder.' }
        $streamIds = @([string] $folders[$inboxDefault - 1].id)
    }
    else {
        $syncIndexes = Read-SyncFolderIndexes -Folders $folders -DefaultIndex $inboxDefault
        $streamIds = @($syncIndexes | ForEach-Object { [string] $folders[$_].id })
    }
    Write-Host ''
    Write-Warning 'Initialization starts at each folder current highest UID. Existing messages will not be imported.'
    Write-Host ('Selected folders: ' + ($streamIds -join ', '))
    $confirmation = Read-Host 'Type INITIALIZE to save cursors, or press Enter to leave the account uninitialized'
    if ($confirmation -ceq 'INITIALIZE') {
        $result = Invoke-ThreadMesh -Method Post -Path "/v1/accounts/$encodedAccountId/initialize" -Body @{ streams = $streamIds }
        Write-Host ('Initialized: ' + ((@($result.cursors.PSObject.Properties.Name)) -join ', ')) -ForegroundColor Green
    }
    else { Write-Host 'Account configured and tested, but no folders were initialized.' -ForegroundColor Yellow }
}
finally {
    $accountPayload = $null
    $plainPassword = $null
}
