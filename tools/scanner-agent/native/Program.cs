using System.Net;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

internal static class Program
{
    private const string AgentVersion = "3.0.0";
    private const int Port = 17873;

    private const string JpegFormat =
        "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}";

    private const string PublicModulus =
        "kIF/3/8JW7CsbWGSaS5S6xrmAoYGAjZg/glzISDjHoY7+jpycZjvw2mohRXwSq4+wkGjb4u0mkCguEAHiisHlNVacV9zpjYAz4TKEgm16MNzX3l5hdHgRD8pPiTl/pu68gaFhS0geWhFmOJNYopbgMD+lQT9pqkHjJADbg8VEGz0Vqrqgl5LBoxW0Gy1+odEH8WV4xmgxDQ7nud9luDqNXBbMO3rYp0VLSXO2Ean6Pnk/X2u27/MR+wDE+4DhSINLI4K81Q3NvnaJPIDmXBHAJ4U5MHuEzoPWbCqygSwt8O+VNYATFjVIhjcFCa3kP2VdY7h4aiNKeGCdbhWazLAow==";

    private const string PublicExponent =
        "AQAB";

    private static readonly string AppDir =
        Path.Combine(
            Environment.GetFolderPath(
                Environment.SpecialFolder.LocalApplicationData
            ),
            "MikroPanelScannerAgent"
        );

    private static readonly string QueueDir =
        Path.Combine(
            AppDir,
            "queue"
        );

    private static readonly string ConfigPath =
        Path.Combine(
            AppDir,
            "config.json"
        );

    private static readonly string LogPath =
        Path.Combine(
            AppDir,
            "agent.log"
        );

    private static string AllowedOrigin = "";

    private static DateTime LastHeartbeat =
        DateTime.MinValue;

    private static DateTime LastScannerPoll =
        DateTime.MinValue;

    private static DateTime LastScanTime =
        DateTime.MinValue;

    private static bool ManualScanRequested;

    private static ScannerSnapshot Scanner =
        new();

    private static string LastError = "";

    private static readonly Mutex SingleInstance =
        new(
            false,
            @"Local\MikroPanelScannerAgent"
        );

    [STAThread]
    private static int Main()
    {
        try
        {
            if (
                !SingleInstance.WaitOne(
                    TimeSpan.Zero,
                    false
                )
            )
            {
                return 0;
            }

            Directory.CreateDirectory(
                AppDir
            );

            Directory.CreateDirectory(
                QueueDir
            );

            LoadConfig();

            using var listener =
                new TcpListener(
                    IPAddress.Loopback,
                    Port
                );

            listener.Start();

            Log(
                $"AGENT_STARTED_VERSION={AgentVersion} PORT={Port}"
            );

            while (true)
            {
                if (listener.Pending())
                {
                    using var client =
                        listener.AcceptTcpClient();

                    HandleClient(
                        client
                    );
                }

                PollScanner();

                Thread.Sleep(
                    80
                );
            }
        }
        catch (Exception ex)
        {
            Log(
                "FATAL="
                + ex
            );

            return 1;
        }
        finally
        {
            try
            {
                SingleInstance.ReleaseMutex();
            }
            catch
            {
            }

            SingleInstance.Dispose();
        }
    }

    private static void HandleClient(
        TcpClient client
    )
    {
        try
        {
            client.ReceiveTimeout = 5000;
            client.SendTimeout = 5000;

            using var stream =
                client.GetStream();

            using var reader =
                new StreamReader(
                    stream,
                    Encoding.ASCII,
                    false,
                    4096,
                    true
                );

            var requestLine =
                reader.ReadLine();

            if (
                string.IsNullOrWhiteSpace(
                    requestLine
                )
            )
            {
                return;
            }

            var headers =
                new Dictionary<string, string>(
                    StringComparer.OrdinalIgnoreCase
                );

            while (true)
            {
                var line =
                    reader.ReadLine();

                if (
                    line is null
                    || line.Length == 0
                )
                {
                    break;
                }

                var colon =
                    line.IndexOf(':');

                if (colon <= 0)
                {
                    continue;
                }

                headers[
                    line[..colon].Trim()
                ] =
                    line[(colon + 1)..]
                        .Trim();
            }

            var parts =
                requestLine.Split(
                    ' ',
                    StringSplitOptions.RemoveEmptyEntries
                );

            if (parts.Length < 2)
            {
                return;
            }

            var method =
                parts[0].ToUpperInvariant();

            var path =
                parts[1]
                    .Split(
                        '?',
                        2
                    )[0];

            headers.TryGetValue(
                "Origin",
                out var origin
            );

            origin ??= "";

            headers.TryGetValue(
                "X-MikroPanel-Token",
                out var scannerToken
            );

            scannerToken ??= "";

            if (method == "OPTIONS")
            {
                SendBytes(
                    stream,
                    "204 No Content",
                    "text/plain",
                    Array.Empty<byte>(),
                    origin
                );

                return;
            }

            if (path == "/health")
            {
                SendJson(
                    stream,
                    "200 OK",
                    new
                    {
                        ok = true,
                        service =
                            "MikroPanel Scanner Agent",
                        version =
                            AgentVersion,
                        native =
                            true,
                    },
                    origin
                );

                return;
            }

            if (path == "/pair")
            {
                if (
                    string.IsNullOrWhiteSpace(
                        origin
                    )
                )
                {
                    SendJson(
                        stream,
                        "400 Bad Request",
                        new
                        {
                            ok = false,
                            message =
                                "Missing browser origin.",
                        },
                        ""
                    );

                    return;
                }

                var normalized =
                    NormalizeOrigin(
                        origin
                    );

                if (
                    !VerifyToken(
                        scannerToken,
                        normalized
                    )
                )
                {
                    SendJson(
                        stream,
                        "403 Forbidden",
                        new
                        {
                            ok = false,
                            message =
                                "Valid MikroPanel scanner authorization token required.",
                        },
                        origin
                    );

                    return;
                }

                if (
                    !OriginMatches(
                        normalized
                    )
                )
                {
                    var previous =
                        AllowedOrigin;

                    AllowedOrigin =
                        normalized;

                    SaveConfig();

                    Log(
                        "AUTHORIZED_PAIR_OR_REPAIR="
                        + normalized
                        + " PREVIOUS="
                        + previous
                    );
                }

                LastHeartbeat =
                    DateTime.UtcNow;

                SendJson(
                    stream,
                    "200 OK",
                    new
                    {
                        ok = true,
                        version =
                            AgentVersion,
                        portablePairing =
                            true,
                        native =
                            true,
                    },
                    origin
                );

                return;
            }

            if (
                !OriginMatches(
                    origin
                )
            )
            {
                SendJson(
                    stream,
                    "403 Forbidden",
                    new
                    {
                        ok = false,
                    },
                    origin
                );

                return;
            }

            LastHeartbeat =
                DateTime.UtcNow;

            if (path == "/status")
            {
                SendJson(
                    stream,
                    "200 OK",
                    new
                    {
                        ok = true,
                        version =
                            AgentVersion,

                        scannerConnected =
                            Scanner.Connected,

                        scannerName =
                            Scanner.Name,

                        autoSupported =
                            Scanner.AutoSupported,

                        feedReady =
                            Scanner.FeedReady,

                        queueCount =
                            QueueCount(),

                        lastError =
                            LastError,

                        native =
                            true,
                    },
                    origin
                );

                return;
            }

            if (path == "/scan-now")
            {
                ManualScanRequested =
                    true;

                SendJson(
                    stream,
                    "202 Accepted",
                    new
                    {
                        ok = true,
                    },
                    origin
                );

                return;
            }

            if (path == "/next-scan")
            {
                var next =
                    GetQueueFiles()
                        .FirstOrDefault();

                if (next is null)
                {
                    SendBytes(
                        stream,
                        "204 No Content",
                        "text/plain",
                        Array.Empty<byte>(),
                        origin
                    );

                    return;
                }

                var bytes =
                    File.ReadAllBytes(
                        next.FullName
                    );

                SendBytes(
                    stream,
                    "200 OK",
                    "image/jpeg",
                    bytes,
                    origin
                );

                try
                {
                    next.Delete();
                }
                catch
                {
                }

                return;
            }

            SendJson(
                stream,
                "404 Not Found",
                new
                {
                    ok = false,
                },
                origin
            );
        }
        catch (Exception ex)
        {
            Log(
                "HTTP_ERROR="
                + ex.Message
            );
        }
    }

    private static void PollScanner()
    {
        var now =
            DateTime.UtcNow;

        if (
            (
                now
                - LastScannerPoll
            ).TotalMilliseconds
            < 1000
        )
        {
            return;
        }

        LastScannerPoll =
            now;

        Scanner =
            GetScannerSnapshot();

        var dashboardActive =
            (
                now
                - LastHeartbeat
            ).TotalSeconds
            < 7;

        var cooldownPassed =
            (
                now
                - LastScanTime
            ).TotalSeconds
            >= 3;

        var queueHasSpace =
            QueueCount()
            < 4;

        var autoTrigger =
            dashboardActive
            && Scanner.Connected
            && Scanner.AutoSupported
            && Scanner.FeedReady;

        var manualTrigger =
            dashboardActive
            && ManualScanRequested
            && Scanner.Connected;

        if (
            !queueHasSpace
            || !cooldownPassed
            || (
                !autoTrigger
                && !manualTrigger
            )
        )
        {
            return;
        }

        ManualScanRequested =
            false;

        try
        {
            InvokeWiaScan();

            LastScanTime =
                DateTime.UtcNow;

            LastError = "";
        }
        catch (Exception ex)
        {
            LastScanTime =
                DateTime.UtcNow;

            LastError =
                ex.Message;

            Log(
                "SCAN_ERROR="
                + ex.Message
            );
        }
    }

    private static ScannerSnapshot
        GetScannerSnapshot()
    {
        object? manager = null;
        object? info = null;
        object? device = null;

        try
        {
            var type =
                Type.GetTypeFromProgID(
                    "WIA.DeviceManager"
                );

            if (type is null)
            {
                return new();
            }

            manager =
                Activator.CreateInstance(
                    type
                );

            if (manager is null)
            {
                return new();
            }

            dynamic dm =
                manager;

            foreach (
                var candidate
                in dm.DeviceInfos
            )
            {
                try
                {
                    if (
                        Convert.ToInt32(
                            candidate.Type
                        ) == 1
                    )
                    {
                        info =
                            candidate;

                        break;
                    }
                }
                catch
                {
                    ReleaseCom(
                        candidate
                    );
                }
            }

            if (info is null)
            {
                return new();
            }

            dynamic scannerInfo =
                info;

            var name =
                ReadStringProperty(
                    scannerInfo.Properties,
                    "Name"
                )
                ?? "WIA Scanner";

            device =
                scannerInfo.Connect();

            dynamic scannerDevice =
                device;

            var status =
                ReadIntProperty(
                    scannerDevice.Properties,
                    3087
                );

            return new()
            {
                Connected =
                    true,

                Name =
                    name,

                AutoSupported =
                    status.HasValue,

                FeedReady =
                    status.HasValue
                    && (
                        status.Value
                        & 1
                    ) == 1,
            };
        }
        catch
        {
            return new();
        }
        finally
        {
            ReleaseCom(
                device
            );

            ReleaseCom(
                info
            );

            ReleaseCom(
                manager
            );
        }
    }

    private static void InvokeWiaScan()
    {
        object? manager = null;
        object? info = null;
        object? device = null;
        object? item = null;
        object? image = null;
        object? processor = null;
        object? converted = null;

        string? tempPath = null;

        try
        {
            var type =
                Type.GetTypeFromProgID(
                    "WIA.DeviceManager"
                )
                ?? throw new InvalidOperationException(
                    "WIA is not available."
                );

            manager =
                Activator.CreateInstance(
                    type
                )
                ?? throw new InvalidOperationException(
                    "Could not initialize WIA."
                );

            dynamic dm =
                manager;

            foreach (
                var candidate
                in dm.DeviceInfos
            )
            {
                if (
                    Convert.ToInt32(
                        candidate.Type
                    ) == 1
                )
                {
                    info =
                        candidate;

                    break;
                }

                ReleaseCom(
                    candidate
                );
            }

            if (info is null)
            {
                throw new InvalidOperationException(
                    "No WIA scanner detected."
                );
            }

            dynamic scannerInfo =
                info;

            device =
                scannerInfo.Connect();

            dynamic scannerDevice =
                device;

            TrySetProperty(
                scannerDevice.Properties,
                3088,
                1
            );

            if (
                Convert.ToInt32(
                    scannerDevice.Items.Count
                ) < 1
            )
            {
                throw new InvalidOperationException(
                    "Scanner exposes no transferable item."
                );
            }

            item =
                scannerDevice.Items.Item(
                    1
                );

            dynamic scannerItem =
                item;

            TrySetProperty(
                scannerItem.Properties,
                6147,
                300
            );

            TrySetProperty(
                scannerItem.Properties,
                6148,
                300
            );

            image =
                scannerItem.Transfer(
                    JpegFormat
                );

            if (image is null)
            {
                throw new InvalidOperationException(
                    "Scanner returned no image."
                );
            }

            var fileName =
                "scan-"
                + DateTime.UtcNow
                    .ToString(
                        "yyyyMMdd-HHmmss-fff"
                    )
                + "-"
                + Guid.NewGuid()
                    .ToString("N")
                + ".jpg";

            tempPath =
                Path.Combine(
                    QueueDir,
                    fileName
                );

            dynamic wiaImage =
                image;

            var format =
                Convert.ToString(
                    wiaImage.FormatID
                )
                ?? "";

            if (
                string.Equals(
                    format,
                    JpegFormat,
                    StringComparison.OrdinalIgnoreCase
                )
            )
            {
                wiaImage.SaveFile(
                    tempPath
                );
            }
            else
            {
                var processType =
                    Type.GetTypeFromProgID(
                        "WIA.ImageProcess"
                    )
                    ?? throw new InvalidOperationException(
                        "WIA image conversion is unavailable."
                    );

                processor =
                    Activator.CreateInstance(
                        processType
                    )
                    ?? throw new InvalidOperationException(
                        "Could not initialize WIA image converter."
                    );

                dynamic ip =
                    processor;

                dynamic filterInfo =
                    ip.FilterInfos[
                        "Convert"
                    ];

                ip.Filters.Add(
                    filterInfo.FilterID
                );

                ip.Filters[
                    1
                ].Properties[
                    "FormatID"
                ].Value =
                    JpegFormat;

                converted =
                    ip.Apply(
                        wiaImage
                    );

                dynamic jpeg =
                    converted;

                jpeg.SaveFile(
                    tempPath
                );
            }

            if (
                !File.Exists(
                    tempPath
                )
                || new FileInfo(
                    tempPath
                ).Length < 1
            )
            {
                throw new InvalidOperationException(
                    "Scanner image file was not created."
                );
            }

            Log(
                "SCAN_OK="
                + tempPath
            );
        }
        catch
        {
            if (
                tempPath is not null
                && File.Exists(
                    tempPath
                )
            )
            {
                try
                {
                    File.Delete(
                        tempPath
                    );
                }
                catch
                {
                }
            }

            throw;
        }
        finally
        {
            ReleaseCom(
                converted
            );

            ReleaseCom(
                processor
            );

            ReleaseCom(
                image
            );

            ReleaseCom(
                item
            );

            ReleaseCom(
                device
            );

            ReleaseCom(
                info
            );

            ReleaseCom(
                manager
            );
        }
    }

    private static int? ReadIntProperty(
        dynamic properties,
        int id
    )
    {
        foreach (
            var raw
            in properties
        )
        {
            object? property =
                raw;

            try
            {
                dynamic p =
                    raw;

                if (
                    Convert.ToInt32(
                        p.PropertyID
                    ) == id
                )
                {
                    return Convert.ToInt32(
                        p.Value
                    );
                }
            }
            catch
            {
            }
            finally
            {
                ReleaseCom(
                    property
                );
            }
        }

        return null;
    }

    private static string? ReadStringProperty(
        dynamic properties,
        string name
    )
    {
        foreach (
            var raw
            in properties
        )
        {
            object? property =
                raw;

            try
            {
                dynamic p =
                    raw;

                if (
                    string.Equals(
                        Convert.ToString(
                            p.Name
                        ),
                        name,
                        StringComparison.OrdinalIgnoreCase
                    )
                )
                {
                    return Convert.ToString(
                        p.Value
                    );
                }
            }
            catch
            {
            }
            finally
            {
                ReleaseCom(
                    property
                );
            }
        }

        return null;
    }

    private static void TrySetProperty(
        dynamic properties,
        int id,
        object value
    )
    {
        foreach (
            var raw
            in properties
        )
        {
            object? property =
                raw;

            try
            {
                dynamic p =
                    raw;

                if (
                    Convert.ToInt32(
                        p.PropertyID
                    ) == id
                )
                {
                    p.Value =
                        value;

                    return;
                }
            }
            catch
            {
            }
            finally
            {
                ReleaseCom(
                    property
                );
            }
        }
    }

    private static bool VerifyToken(
        string token,
        string origin
    )
    {
        try
        {
            if (
                string.IsNullOrWhiteSpace(
                    token
                )
            )
            {
                return false;
            }

            var parts =
                token.Split('.');

            if (parts.Length != 2)
            {
                return false;
            }

            var payload =
                Base64UrlDecode(
                    parts[0]
                );

            var signature =
                Base64UrlDecode(
                    parts[1]
                );

            using var json =
                JsonDocument.Parse(
                    payload
                );

            var root =
                json.RootElement;

            if (
                root.GetProperty(
                    "iss"
                ).GetString()
                != "mikropanel"
            )
            {
                return false;
            }

            if (
                root.GetProperty(
                    "aud"
                ).GetString()
                != "scanner-agent"
            )
            {
                return false;
            }

            var tokenOrigin =
                NormalizeOrigin(
                    root.GetProperty(
                        "origin"
                    ).GetString()
                    ?? ""
                );

            if (
                !string.Equals(
                    tokenOrigin,
                    NormalizeOrigin(
                        origin
                    ),
                    StringComparison.OrdinalIgnoreCase
                )
            )
            {
                return false;
            }

            var exp =
                root.GetProperty(
                    "exp"
                ).GetInt64();

            var now =
                DateTimeOffset.UtcNow
                    .ToUnixTimeSeconds();

            if (
                exp <= now
                || exp > now + 600
            )
            {
                return false;
            }

            using var rsa =
                RSA.Create();

            rsa.ImportParameters(
                new RSAParameters
                {
                    Modulus =
                        Convert.FromBase64String(
                            PublicModulus
                        ),

                    Exponent =
                        Convert.FromBase64String(
                            PublicExponent
                        ),
                }
            );

            return rsa.VerifyData(
                Encoding.UTF8.GetBytes(
                    parts[0]
                ),
                signature,
                HashAlgorithmName.SHA256,
                RSASignaturePadding.Pkcs1
            );
        }
        catch (Exception ex)
        {
            Log(
                "TOKEN_VERIFY_ERROR="
                + ex.Message
            );

            return false;
        }
    }

    private static byte[] Base64UrlDecode(
        string value
    )
    {
        value =
            value.Replace(
                '-',
                '+'
            )
            .Replace(
                '_',
                '/'
            );

        value +=
            new string(
                '=',
                (
                    4
                    - value.Length % 4
                ) % 4
            );

        return Convert.FromBase64String(
            value
        );
    }

    private static string NormalizeOrigin(
        string origin
    )
    {
        return origin
            .Trim()
            .TrimEnd('/');
    }

    private static bool OriginMatches(
        string origin
    )
    {
        if (
            string.IsNullOrWhiteSpace(
                origin
            )
            || string.IsNullOrWhiteSpace(
                AllowedOrigin
            )
        )
        {
            return false;
        }

        return string.Equals(
            NormalizeOrigin(
                origin
            ),
            NormalizeOrigin(
                AllowedOrigin
            ),
            StringComparison.OrdinalIgnoreCase
        );
    }

    private static void LoadConfig()
    {
        if (
            !File.Exists(
                ConfigPath
            )
        )
        {
            return;
        }

        try
        {
            using var json =
                JsonDocument.Parse(
                    File.ReadAllText(
                        ConfigPath
                    )
                );

            if (
                json.RootElement
                    .TryGetProperty(
                        "allowedOrigin",
                        out var origin
                    )
            )
            {
                AllowedOrigin =
                    NormalizeOrigin(
                        origin.GetString()
                        ?? ""
                    );
            }
        }
        catch (Exception ex)
        {
            Log(
                "CONFIG_READ_ERROR="
                + ex.Message
            );
        }
    }

    private static void SaveConfig()
    {
        var json =
            JsonSerializer.Serialize(
                new
                {
                    allowedOrigin =
                        AllowedOrigin,

                    version =
                        AgentVersion,
                }
            );

        File.WriteAllText(
            ConfigPath,
            json,
            Encoding.UTF8
        );
    }

    private static FileInfo[]
        GetQueueFiles()
    {
        Directory.CreateDirectory(
            QueueDir
        );

        return new DirectoryInfo(
            QueueDir
        )
            .GetFiles(
                "*.jpg"
            )
            .OrderBy(
                x => x.CreationTimeUtc
            )
            .ToArray();
    }

    private static int QueueCount()
    {
        return GetQueueFiles()
            .Length;
    }

    private static void SendJson(
        NetworkStream stream,
        string status,
        object value,
        string origin
    )
    {
        SendBytes(
            stream,
            status,
            "application/json; charset=utf-8",
            JsonSerializer.SerializeToUtf8Bytes(
                value
            ),
            origin
        );
    }

    private static void SendBytes(
        NetworkStream stream,
        string status,
        string contentType,
        byte[] body,
        string origin
    )
    {
        var headers =
            new StringBuilder();

        headers.Append(
            "HTTP/1.1 "
        );

        headers.Append(
            status
        );

        headers.Append(
            "\r\n"
        );

        headers.Append(
            "Content-Type: "
        );

        headers.Append(
            contentType
        );

        headers.Append(
            "\r\n"
        );

        headers.Append(
            "Content-Length: "
        );

        headers.Append(
            body.Length
        );

        headers.Append(
            "\r\n"
        );

        headers.Append(
            "Cache-Control: no-store\r\n"
        );

        headers.Append(
            "Access-Control-Allow-Methods: GET, OPTIONS\r\n"
        );

        headers.Append(
            "Access-Control-Allow-Headers: Content-Type, X-MikroPanel-Token\r\n"
        );

        headers.Append(
            "Access-Control-Allow-Private-Network: true\r\n"
        );

        if (
            !string.IsNullOrWhiteSpace(
                origin
            )
        )
        {
            headers.Append(
                "Access-Control-Allow-Origin: "
            );

            headers.Append(
                origin
            );

            headers.Append(
                "\r\nVary: Origin\r\n"
            );
        }

        headers.Append(
            "Connection: close\r\n\r\n"
        );

        var headerBytes =
            Encoding.ASCII.GetBytes(
                headers.ToString()
            );

        stream.Write(
            headerBytes,
            0,
            headerBytes.Length
        );

        if (body.Length > 0)
        {
            stream.Write(
                body,
                0,
                body.Length
            );
        }

        stream.Flush();
    }

    private static void ReleaseCom(
        object? value
    )
    {
        if (
            value is null
            || !Marshal.IsComObject(
                value
            )
        )
        {
            return;
        }

        try
        {
            Marshal.FinalReleaseComObject(
                value
            );
        }
        catch
        {
        }
    }

    private static void Log(
        string message
    )
    {
        try
        {
            Directory.CreateDirectory(
                AppDir
            );

            File.AppendAllText(
                LogPath,
                DateTime.Now.ToString(
                    "yyyy-MM-dd HH:mm:ss"
                )
                + " "
                + message
                + Environment.NewLine,
                Encoding.UTF8
            );
        }
        catch
        {
        }
    }

    private sealed class ScannerSnapshot
    {
        public bool Connected
        {
            get;
            init;
        }

        public string? Name
        {
            get;
            init;
        }

        public bool AutoSupported
        {
            get;
            init;
        }

        public bool FeedReady
        {
            get;
            init;
        }
    }
}
