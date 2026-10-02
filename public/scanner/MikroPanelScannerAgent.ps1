param(
    [switch]$Install,
    [switch]$ResetPair,
    [string]$PairOrigin = ""
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = "Stop"

$AgentVersion = "1.0.0"
$Port = 17873

$AppDir =
    Join-Path `
        $env:LOCALAPPDATA `
        "MikroPanelScannerAgent"

$QueueDir =
    Join-Path `
        $AppDir `
        "queue"

$ConfigPath =
    Join-Path `
        $AppDir `
        "config.json"

$LogPath =
    Join-Path `
        $AppDir `
        "agent.log"

$InstalledScript =
    Join-Path `
        $AppDir `
        "MikroPanelScannerAgent.ps1"

$TaskName =
    "MikroPanel Scanner Agent"

$PowerShellExe =
    "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"

$JpegFormat =
    "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}"

New-Item `
    -ItemType Directory `
    -Force `
    -Path $AppDir, $QueueDir `
    | Out-Null

function Write-AgentLog {
    param(
        [string]$Message
    )

    $line =
        "{0} {1}" -f `
        (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), `
        $Message

    try {
        Add-Content `
            -Path $LogPath `
            -Value $line `
            -Encoding UTF8
    } catch {
    }
}

$script:AllowedOrigin = ""

if (Test-Path $ConfigPath) {
    try {
        $cfg =
            Get-Content `
                -Raw `
                -Path $ConfigPath `
                | ConvertFrom-Json

        if ($cfg.allowedOrigin) {
            $script:AllowedOrigin =
                [string]$cfg.allowedOrigin
        }
    } catch {
        Write-AgentLog "CONFIG_READ_ERROR=$($_.Exception.Message)"
    }
}

function Save-AgentConfig {
    @{
        allowedOrigin =
            $script:AllowedOrigin

        version =
            $AgentVersion
    } |
        ConvertTo-Json |
        Set-Content `
            -Path $ConfigPath `
            -Encoding UTF8
}

if ($Install) {
    if (
        -not [string]::IsNullOrWhiteSpace(
            $PairOrigin
        )
    ) {
        $script:AllowedOrigin =
            $PairOrigin.TrimEnd("/")

        Save-AgentConfig
    }

    try {
        & schtasks.exe `
            /End `
            /TN $TaskName `
            2>$null `
            | Out-Null
    } catch {
    }

    Copy-Item `
        -Force `
        -Path $PSCommandPath `
        -Destination $InstalledScript

    $taskCommand =
        '"' +
        $PowerShellExe +
        '" -NoProfile -STA -WindowStyle Hidden' +
        ' -ExecutionPolicy Bypass -File "' +
        $InstalledScript +
        '"'

    & schtasks.exe `
        /Create `
        /TN $TaskName `
        /SC ONLOGON `
        /RL LIMITED `
        /TR $taskCommand `
        /F `
        | Out-Null

    Start-Process `
        -FilePath $PowerShellExe `
        -WindowStyle Hidden `
        -ArgumentList @(
            "-NoProfile",
            "-STA",
            "-ExecutionPolicy",
            "Bypass",
            "-File",
            "`"$InstalledScript`""
        )

    Write-Host ""
    Write-Host "MIKROPANEL_SCANNER_AGENT_INSTALLED=PASS"
    Write-Host "PORT=$Port"
    Write-Host "PAIR_ORIGIN=$script:AllowedOrigin"
    Write-Host "TASK=$TaskName"
    Write-Host ""
    Write-Host "Return to MikroPanel and keep the Client form open."

    exit 0
}

if ($ResetPair) {
    $script:AllowedOrigin = ""
    Save-AgentConfig

    Write-Host "SCANNER_AGENT_PAIRING_RESET=PASS"
    exit 0
}

function Release-Com {
    param(
        [object]$Object
    )

    if (
        $null -ne $Object
        -and [Runtime.InteropServices.Marshal]::IsComObject(
            $Object
        )
    ) {
        try {
            [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject(
                $Object
            )
        } catch {
        }
    }
}

function Get-InfoName {
    param(
        [object]$Info
    )

    try {
        foreach (
            $property
            in $Info.Properties
        ) {
            if (
                [string]$property.Name
                -eq "Name"
            ) {
                return [string]$property.Value
            }
        }
    } catch {
    }

    try {
        return [string]$Info.DeviceID
    } catch {
        return "WIA Scanner"
    }
}

function Get-PropertyValue {
    param(
        [object]$Properties,
        [int]$PropertyId
    )

    try {
        foreach (
            $property
            in $Properties
        ) {
            if (
                [int]$property.PropertyID
                -eq $PropertyId
            ) {
                return $property.Value
            }
        }
    } catch {
    }

    return $null
}

function Set-PropertyValue {
    param(
        [object]$Properties,
        [int]$PropertyId,
        [object]$Value
    )

    try {
        foreach (
            $property
            in $Properties
        ) {
            if (
                [int]$property.PropertyID
                -eq $PropertyId
            ) {
                try {
                    $property.Value =
                        $Value

                    return $true
                } catch {
                    return $false
                }
            }
        }
    } catch {
    }

    return $false
}

function Get-ScannerSnapshot {
    $manager = $null
    $info = $null
    $device = $null

    try {
        $manager =
            New-Object `
                -ComObject `
                "WIA.DeviceManager"

        foreach (
            $candidate
            in $manager.DeviceInfos
        ) {
            if (
                [int]$candidate.Type
                -eq 1
            ) {
                $info =
                    $candidate

                break
            }
        }

        if ($null -eq $info) {
            return [pscustomobject]@{
                connected =
                    $false

                name =
                    $null

                autoSupported =
                    $false

                feedReady =
                    $false

                status =
                    $null
            }
        }

        $name =
            Get-InfoName `
                -Info $info

        $device =
            $info.Connect()

        /*
         * WIA property 3087:
         * DOCUMENT_HANDLING_STATUS
         *
         * FEED_READY bit = 0x00000001
         */
        $status =
            Get-PropertyValue `
                -Properties $device.Properties `
                -PropertyId 3087

        $autoSupported =
            $null -ne $status

        $feedReady =
            $false

        if ($autoSupported) {
            $feedReady =
                (
                    ([int]$status -band 1)
                    -ne 0
                )
        }

        return [pscustomobject]@{
            connected =
                $true

            name =
                $name

            autoSupported =
                $autoSupported

            feedReady =
                $feedReady

            status =
                $status
        }
    } catch {
        Write-AgentLog `
            "SCANNER_STATUS_ERROR=$($_.Exception.Message)"

        return [pscustomobject]@{
            connected =
                $false

            name =
                $null

            autoSupported =
                $false

            feedReady =
                $false

            status =
                $null
        }
    } finally {
        Release-Com $device
        Release-Com $info
        Release-Com $manager
    }
}

function Invoke-WiaScan {
    $manager = $null
    $info = $null
    $device = $null
    $item = $null
    $image = $null
    $processor = $null

    try {
        $manager =
            New-Object `
                -ComObject `
                "WIA.DeviceManager"

        foreach (
            $candidate
            in $manager.DeviceInfos
        ) {
            if (
                [int]$candidate.Type
                -eq 1
            ) {
                $info =
                    $candidate

                break
            }
        }

        if ($null -eq $info) {
            throw "No WIA scanner detected."
        }

        $device =
            $info.Connect()

        /*
         * Ask compatible WIA drivers to use
         * the feeder when it is available.
         *
         * Property 3088 =
         * DOCUMENT_HANDLING_SELECT.
         * FEEDER = 1.
         */
        [void](
            Set-PropertyValue `
                -Properties $device.Properties `
                -PropertyId 3088 `
                -Value 1
        )

        if (
            $device.Items.Count
            -lt 1
        ) {
            throw "Scanner exposes no transferable item."
        }

        $item =
            $device.Items.Item(1)

        /*
         * Best effort 300 DPI.
         * Unsupported drivers simply retain
         * their own defaults.
         */
        [void](
            Set-PropertyValue `
                -Properties $item.Properties `
                -PropertyId 6147 `
                -Value 300
        )

        [void](
            Set-PropertyValue `
                -Properties $item.Properties `
                -PropertyId 6148 `
                -Value 300
        )

        $image =
            $item.Transfer(
                $JpegFormat
            )

        if ($null -eq $image) {
            throw "Scanner returned no image."
        }

        /*
         * Some drivers return their preferred
         * format even when JPEG was requested.
         * Convert it to JPEG before queueing.
         */
        if (
            [string]$image.FormatID
            -ne $JpegFormat
        ) {
            $processor =
                New-Object `
                    -ComObject `
                    "WIA.ImageProcess"

            $processor.Filters.Add(
                $processor.FilterInfos
                    .Item("Convert")
                    .FilterID
            )

            $processor.Filters
                .Item(1)
                .Properties
                .Item("FormatID")
                .Value =
                    $JpegFormat

            $image =
                $processor.Apply(
                    $image
                )
        }

        $filename =
            "scan-{0}-{1}.jpg" -f `
            (Get-Date -Format "yyyyMMdd-HHmmss-fff"), `
            ([guid]::NewGuid().ToString("N"))

        $path =
            Join-Path `
                $QueueDir `
                $filename

        if (Test-Path $path) {
            Remove-Item `
                -Force `
                $path
        }

        $image.SaveFile(
            $path
        )

        Write-AgentLog `
            "SCAN_OK=$filename"

        return $path
    } catch {
        Write-AgentLog `
            "SCAN_ERROR=$($_.Exception.Message)"

        throw
    } finally {
        Release-Com $processor
        Release-Com $item
        Release-Com $device
        Release-Com $info
        Release-Com $manager
    }
}

function Get-QueueFiles {
    @(
        Get-ChildItem `
            -Path $QueueDir `
            -Filter "*.jpg" `
            -File `
            -ErrorAction SilentlyContinue |
        Sort-Object `
            CreationTime
    )
}

function Get-QueueCount {
    @(
        Get-QueueFiles
    ).Count
}

function Send-Bytes {
    param(
        [System.IO.Stream]$Stream,
        [string]$Status,
        [string]$ContentType,
        [byte[]]$Body,
        [string]$Origin = ""
    )

    if ($null -eq $Body) {
        $Body =
            New-Object byte[] 0
    }

    $headers =
        New-Object `
            System.Collections.Generic.List[string]

    $headers.Add(
        "HTTP/1.1 $Status"
    )

    $headers.Add(
        "Content-Type: $ContentType"
    )

    $headers.Add(
        "Content-Length: $($Body.Length)"
    )

    $headers.Add(
        "Cache-Control: no-store"
    )

    $headers.Add(
        "Access-Control-Allow-Methods: GET, OPTIONS"
    )

    $headers.Add(
        "Access-Control-Allow-Headers: Content-Type"
    )

    /*
     * Supports browsers that still perform
     * Private Network Access preflights.
     */
    $headers.Add(
        "Access-Control-Allow-Private-Network: true"
    )

    if (
        -not [string]::IsNullOrWhiteSpace(
            $Origin
        )
    ) {
        $headers.Add(
            "Access-Control-Allow-Origin: $Origin"
        )

        $headers.Add(
            "Vary: Origin"
        )
    }

    $headers.Add(
        "Connection: close"
    )

    $rawHeaders =
        (
            $headers
            -join "`r`n"
        ) +
        "`r`n`r`n"

    $headerBytes =
        [Text.Encoding]::ASCII.GetBytes(
            $rawHeaders
        )

    $Stream.Write(
        $headerBytes,
        0,
        $headerBytes.Length
    )

    if (
        $Body.Length
        -gt 0
    ) {
        $Stream.Write(
            $Body,
            0,
            $Body.Length
        )
    }

    $Stream.Flush()
}

function Send-Json {
    param(
        [System.IO.Stream]$Stream,
        [string]$Status,
        [object]$Object,
        [string]$Origin = ""
    )

    $json =
        $Object |
        ConvertTo-Json `
            -Compress `
            -Depth 5

    $bytes =
        [Text.Encoding]::UTF8.GetBytes(
            $json
        )

    Send-Bytes `
        -Stream $Stream `
        -Status $Status `
        -ContentType "application/json; charset=utf-8" `
        -Body $bytes `
        -Origin $Origin
}

function Test-PairedOrigin {
    param(
        [string]$Origin
    )

    if (
        [string]::IsNullOrWhiteSpace(
            $Origin
        )
        -or [string]::IsNullOrWhiteSpace(
            $script:AllowedOrigin
        )
    ) {
        return $false
    }

    return [string]::Equals(
        $Origin.TrimEnd("/"),
        $script:AllowedOrigin.TrimEnd("/"),
        [StringComparison]::OrdinalIgnoreCase
    )
}

$script:CachedScanner =
    [pscustomobject]@{
        connected =
            $false

        name =
            $null

        autoSupported =
            $false

        feedReady =
            $false

        status =
            $null
    }

$script:LastError = ""
$script:LastHeartbeat = [datetime]::MinValue
$script:LastScannerPoll = [datetime]::MinValue
$script:LastScanTime = [datetime]::MinValue
$script:ManualScanRequested = $false

$listener =
    New-Object `
        System.Net.Sockets.TcpListener(
            [Net.IPAddress]::Loopback,
            $Port
        )

try {
    $listener.Start()
} catch {
    Write-AgentLog `
        "LISTENER_ERROR=$($_.Exception.Message)"

    exit 1
}

Write-AgentLog `
    "AGENT_STARTED_VERSION=$AgentVersion PORT=$Port"

while ($true) {
    /*
     * Browser requests.
     */
    if ($listener.Pending()) {
        $client = $null
        $stream = $null
        $reader = $null

        try {
            $client =
                $listener.AcceptTcpClient()

            $stream =
                $client.GetStream()

            $reader =
                New-Object `
                    System.IO.StreamReader(
                        $stream,
                        [Text.Encoding]::ASCII,
                        $false,
                        4096,
                        $true
                    )

            $requestLine =
                $reader.ReadLine()

            if (
                [string]::IsNullOrWhiteSpace(
                    $requestLine
                )
            ) {
                continue
            }

            $headers = @{}

            while ($true) {
                $line =
                    $reader.ReadLine()

                if (
                    $null -eq $line
                    -or $line -eq ""
                ) {
                    break
                }

                $colon =
                    $line.IndexOf(":")

                if ($colon -gt 0) {
                    $key =
                        $line
                            .Substring(
                                0,
                                $colon
                            )
                            .Trim()
                            .ToLowerInvariant()

                    $value =
                        $line
                            .Substring(
                                $colon + 1
                            )
                            .Trim()

                    $headers[$key] =
                        $value
                }
            }

            $requestParts =
                $requestLine.Split(" ")

            $method =
                $requestParts[0]
                    .ToUpperInvariant()

            $target =
                $requestParts[1]

            $path =
                $target.Split("?")[0]

            $origin = ""

            if (
                $headers.ContainsKey(
                    "origin"
                )
            ) {
                $origin =
                    [string]$headers[
                        "origin"
                    ]
            }

            if ($method -eq "OPTIONS") {
                Send-Bytes `
                    -Stream $stream `
                    -Status "204 No Content" `
                    -ContentType "text/plain" `
                    -Body (
                        New-Object byte[] 0
                    ) `
                    -Origin $origin

                continue
            }

            if ($path -eq "/pair") {
                if (
                    [string]::IsNullOrWhiteSpace(
                        $origin
                    )
                ) {
                    Send-Json `
                        -Stream $stream `
                        -Status "400 Bad Request" `
                        -Object @{
                            ok =
                                $false

                            message =
                                "Missing browser origin."
                        }

                    continue
                }

                $normalized =
                    $origin.TrimEnd("/")

                if (
                    [string]::IsNullOrWhiteSpace(
                        $script:AllowedOrigin
                    )
                ) {
                    $script:AllowedOrigin =
                        $normalized

                    Save-AgentConfig

                    Write-AgentLog `
                        "PAIRED_ORIGIN=$normalized"
                }

                if (
                    -not (
                        Test-PairedOrigin `
                            -Origin $normalized
                    )
                ) {
                    Send-Json `
                        -Stream $stream `
                        -Status "403 Forbidden" `
                        -Object @{
                            ok =
                                $false

                            message =
                                "Scanner Agent is paired to another MikroPanel origin."
                        } `
                        -Origin $origin

                    continue
                }

                $script:LastHeartbeat =
                    Get-Date

                Send-Json `
                    -Stream $stream `
                    -Status "200 OK" `
                    -Object @{
                        ok =
                            $true

                        version =
                            $AgentVersion
                    } `
                    -Origin $origin

                continue
            }

            if ($path -eq "/status") {
                if (
                    -not (
                        Test-PairedOrigin `
                            -Origin $origin
                    )
                ) {
                    Send-Json `
                        -Stream $stream `
                        -Status "403 Forbidden" `
                        -Object @{
                            ok =
                                $false
                        } `
                        -Origin $origin

                    continue
                }

                $script:LastHeartbeat =
                    Get-Date

                Send-Json `
                    -Stream $stream `
                    -Status "200 OK" `
                    -Object @{
                        ok =
                            $true

                        version =
                            $AgentVersion

                        scannerConnected =
                            [bool]$script:CachedScanner.connected

                        scannerName =
                            $script:CachedScanner.name

                        autoSupported =
                            [bool]$script:CachedScanner.autoSupported

                        feedReady =
                            [bool]$script:CachedScanner.feedReady

                        queueCount =
                            Get-QueueCount

                        lastError =
                            $script:LastError
                    } `
                    -Origin $origin

                continue
            }

            if ($path -eq "/next-scan") {
                if (
                    -not (
                        Test-PairedOrigin `
                            -Origin $origin
                    )
                ) {
                    Send-Json `
                        -Stream $stream `
                        -Status "403 Forbidden" `
                        -Object @{
                            ok =
                                $false
                        } `
                        -Origin $origin

                    continue
                }

                $script:LastHeartbeat =
                    Get-Date

                $next =
                    Get-QueueFiles |
                    Select-Object -First 1

                if ($null -eq $next) {
                    Send-Bytes `
                        -Stream $stream `
                        -Status "204 No Content" `
                        -ContentType "text/plain" `
                        -Body (
                            New-Object byte[] 0
                        ) `
                        -Origin $origin

                    continue
                }

                $bytes =
                    [IO.File]::ReadAllBytes(
                        $next.FullName
                    )

                Send-Bytes `
                    -Stream $stream `
                    -Status "200 OK" `
                    -ContentType "image/jpeg" `
                    -Body $bytes `
                    -Origin $origin

                try {
                    Remove-Item `
                        -Force `
                        $next.FullName
                } catch {
                }

                continue
            }

            if ($path -eq "/scan-now") {
                if (
                    -not (
                        Test-PairedOrigin `
                            -Origin $origin
                    )
                ) {
                    Send-Json `
                        -Stream $stream `
                        -Status "403 Forbidden" `
                        -Object @{
                            ok =
                                $false
                        } `
                        -Origin $origin

                    continue
                }

                $script:LastHeartbeat =
                    Get-Date

                $script:ManualScanRequested =
                    $true

                Send-Json `
                    -Stream $stream `
                    -Status "202 Accepted" `
                    -Object @{
                        ok =
                            $true
                    } `
                    -Origin $origin

                continue
            }

            if ($path -eq "/health") {
                Send-Json `
                    -Stream $stream `
                    -Status "200 OK" `
                    -Object @{
                        ok =
                            $true

                        service =
                            "MikroPanel Scanner Agent"

                        version =
                            $AgentVersion
                    } `
                    -Origin $origin

                continue
            }

            Send-Json `
                -Stream $stream `
                -Status "404 Not Found" `
                -Object @{
                    ok =
                        $false
                } `
                -Origin $origin
        } catch {
            Write-AgentLog `
                "HTTP_ERROR=$($_.Exception.Message)"
        } finally {
            if ($null -ne $reader) {
                try {
                    $reader.Dispose()
                } catch {
                }
            }

            if ($null -ne $stream) {
                try {
                    $stream.Dispose()
                } catch {
                }
            }

            if ($null -ne $client) {
                try {
                    $client.Close()
                } catch {
                }
            }
        }
    }

    /*
     * Poll WIA hardware.
     */
    $now =
        Get-Date

    if (
        (
            $now
            - $script:LastScannerPoll
        ).TotalMilliseconds
        -ge 1000
    ) {
        $script:LastScannerPoll =
            $now

        $script:CachedScanner =
            Get-ScannerSnapshot

        $dashboardActive =
            (
                (
                    $now
                    - $script:LastHeartbeat
                ).TotalSeconds
                -lt 7
            )

        $scanCooldownPassed =
            (
                (
                    $now
                    - $script:LastScanTime
                ).TotalSeconds
                -ge 3
            )

        $queueHasSpace =
            (
                Get-QueueCount
            ) -lt 4

        $autoTrigger =
            (
                $dashboardActive
                -and $script:CachedScanner.connected
                -and $script:CachedScanner.autoSupported
                -and $script:CachedScanner.feedReady
            )

        $manualTrigger =
            (
                $dashboardActive
                -and $script:ManualScanRequested
                -and $script:CachedScanner.connected
            )

        if (
            $queueHasSpace
            -and $scanCooldownPassed
            -and (
                $autoTrigger
                -or $manualTrigger
            )
        ) {
            $script:ManualScanRequested =
                $false

            try {
                [void](
                    Invoke-WiaScan
                )

                $script:LastScanTime =
                    Get-Date

                $script:LastError =
                    ""
            } catch {
                $script:LastScanTime =
                    Get-Date

                $script:LastError =
                    $_.Exception.Message
            }
        }
    }

    Start-Sleep `
        -Milliseconds 80
}
