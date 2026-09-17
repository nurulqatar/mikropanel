using System.Diagnostics;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Text;

internal static class Program
{
    private const string TaskName =
        "MikroPanel Scanner Agent";

    private const uint MbIconInformation =
        0x00000040;

    private const uint MbIconError =
        0x00000010;

    [DllImport(
        "user32.dll",
        CharSet = CharSet.Unicode,
        SetLastError = true
    )]
    private static extern int MessageBoxW(
        IntPtr hWnd,
        string text,
        string caption,
        uint type
    );

    [STAThread]
    private static int Main(
        string[] args
    )
    {
        try
        {
            if (
                args.Any(
                    x => string.Equals(
                        x,
                        "--uninstall",
                        StringComparison.OrdinalIgnoreCase
                    )
                )
            )
            {
                return Uninstall();
            }

            return Install();
        }
        catch (Exception ex)
        {
            ShowError(
                "Installation failed.\n\n"
                + ex.Message
            );

            return 1;
        }
    }

    private static int Install()
    {
        var tempScript =
            Path.Combine(
                Path.GetTempPath(),
                "MikroPanelScannerAgent-"
                + Guid.NewGuid().ToString("N")
                + ".ps1"
            );

        try
        {
            using var resource =
                Assembly
                    .GetExecutingAssembly()
                    .GetManifestResourceStream(
                        "MikroPanelScannerAgent.ps1"
                    );

            if (resource is null)
            {
                throw new InvalidOperationException(
                    "Embedded scanner agent was not found."
                );
            }

            using (
                var file =
                    File.Create(
                        tempScript
                    )
            )
            {
                resource.CopyTo(
                    file
                );
            }

            var windows =
                Environment.GetFolderPath(
                    Environment.SpecialFolder.Windows
                );

            var powershell =
                Path.Combine(
                    windows,
                    "System32",
                    "WindowsPowerShell",
                    "v1.0",
                    "powershell.exe"
                );

            if (!File.Exists(powershell))
            {
                powershell =
                    "powershell.exe";
            }

            var psi =
                new ProcessStartInfo
                {
                    FileName =
                        powershell,

                    UseShellExecute =
                        false,

                    CreateNoWindow =
                        true,

                    RedirectStandardOutput =
                        true,

                    RedirectStandardError =
                        true,
                };

            psi.ArgumentList.Add(
                "-NoProfile"
            );

            psi.ArgumentList.Add(
                "-ExecutionPolicy"
            );

            psi.ArgumentList.Add(
                "Bypass"
            );

            psi.ArgumentList.Add(
                "-File"
            );

            psi.ArgumentList.Add(
                tempScript
            );

            psi.ArgumentList.Add(
                "-Install"
            );

            using var process =
                Process.Start(
                    psi
                )
                ?? throw new InvalidOperationException(
                    "Could not start Windows PowerShell."
                );

            var stdout =
                process.StandardOutput
                    .ReadToEnd();

            var stderr =
                process.StandardError
                    .ReadToEnd();

            process.WaitForExit();

            if (process.ExitCode != 0)
            {
                throw new InvalidOperationException(
                    "Scanner Agent installer returned "
                    + process.ExitCode
                    + ".\n\n"
                    + Tail(
                        stderr
                        + "\n"
                        + stdout,
                        1400
                    )
                );
            }

            if (
                ! stdout.Contains(
                    "MIKROPANEL_SCANNER_AGENT_INSTALLED=PASS",
                    StringComparison.Ordinal
                )
            )
            {
                throw new InvalidOperationException(
                    "Scanner Agent installation did not return confirmation.\n\n"
                    + Tail(
                        stdout
                        + "\n"
                        + stderr,
                        1400
                    )
                );
            }

            MessageBoxW(
                IntPtr.Zero,
                "MikroPanel Scanner Agent installed successfully.\n\n"
                + "It will start automatically with Windows.\n"
                + "Return to MikroPanel and refresh the Client form.",
                "MikroPanel Scanner Agent",
                MbIconInformation
            );

            return 0;
        }
        finally
        {
            try
            {
                if (
                    File.Exists(
                        tempScript
                    )
                )
                {
                    File.Delete(
                        tempScript
                    );
                }
            }
            catch
            {
            }
        }
    }

    private static int Uninstall()
    {
        try
        {
            RunHidden(
                "schtasks.exe",
                "/End",
                "/TN",
                TaskName
            );
        }
        catch
        {
        }

        try
        {
            RunHidden(
                "schtasks.exe",
                "/Delete",
                "/TN",
                TaskName,
                "/F"
            );
        }
        catch
        {
        }

        var appDir =
            Path.Combine(
                Environment.GetFolderPath(
                    Environment.SpecialFolder.LocalApplicationData
                ),
                "MikroPanelScannerAgent"
            );

        try
        {
            if (
                Directory.Exists(
                    appDir
                )
            )
            {
                Directory.Delete(
                    appDir,
                    true
                );
            }
        }
        catch
        {
        }

        MessageBoxW(
            IntPtr.Zero,
            "MikroPanel Scanner Agent was removed.",
            "MikroPanel Scanner Agent",
            MbIconInformation
        );

        return 0;
    }

    private static void RunHidden(
        string fileName,
        params string[] arguments
    )
    {
        var psi =
            new ProcessStartInfo
            {
                FileName =
                    fileName,

                UseShellExecute =
                    false,

                CreateNoWindow =
                    true,

                RedirectStandardOutput =
                    true,

                RedirectStandardError =
                    true,
            };

        foreach (
            var argument
            in arguments
        )
        {
            psi.ArgumentList.Add(
                argument
            );
        }

        using var process =
            Process.Start(
                psi
            );

        process?.WaitForExit(
            10000
        );
    }

    private static string Tail(
        string value,
        int maxLength
    )
    {
        value =
            value.Trim();

        if (
            value.Length
            <= maxLength
        )
        {
            return value;
        }

        return value[
            (value.Length - maxLength)..
        ];
    }

    private static void ShowError(
        string message
    )
    {
        MessageBoxW(
            IntPtr.Zero,
            message,
            "MikroPanel Scanner Agent",
            MbIconError
        );
    }
}
