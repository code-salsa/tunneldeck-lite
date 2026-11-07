using System;
using System.ComponentModel;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.Reflection;
using System.Security.Principal;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace TunnelDeck.Uninstaller
{
    static class Program
    {
        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            // 1. Ensure Administrator privileges for Program Files cleanup
            if (!IsAdministrator())
            {
                try
                {
                    ProcessStartInfo psi = new ProcessStartInfo
                    {
                        FileName = Application.ExecutablePath,
                        Arguments = string.Join(" ", args),
                        UseShellExecute = true,
                        Verb = "runas"
                    };
                    Process.Start(psi);
                    return;
                }
                catch
                {
                    MessageBox.Show(
                        "Administrator privileges are required to uninstall TunnelDeck Lite.",
                        "Elevation Required",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Warning
                    );
                    return;
                }
            }

            bool isSilent = false;
            foreach (string arg in args)
            {
                if (arg.Equals("--silent", StringComparison.OrdinalIgnoreCase) ||
                    arg.Equals("/S", StringComparison.OrdinalIgnoreCase))
                {
                    isSilent = true;
                }
            }

            if (isSilent)
            {
                RunSilentUninstall();
            }
            else
            {
                Application.Run(new UninstallWizardForm());
            }
        }

        public static bool IsAdministrator()
        {
            using (WindowsIdentity identity = WindowsIdentity.GetCurrent())
            {
                WindowsPrincipal principal = new WindowsPrincipal(identity);
                return principal.IsInRole(WindowsBuiltInRole.Administrator);
            }
        }

        private static void RunSilentUninstall()
        {
            string installDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');
            UninstallEngine engine = new UninstallEngine(installDir);
            engine.Execute(null);
        }
    }

    public class UninstallWizardForm : Form
    {
        private const string AppName = "TunnelDeck";
        private const string AppRegKey = "TunnelDeck";

        private string installDir;
        private Icon appIcon;
        private Image logoImage;

        // Wizard Panels
        private int currentPage = 0;
        private Panel pnlConfirm;
        private Panel pnlUninstalling;
        private Panel pnlFinished;

        // Bottom Controls
        private Panel pnlBottom;
        private Button btnAction;
        private Button btnCancel;

        // Uninstalling Controls
        private Label lblStatus;
        private Label lblDetail;
        private ProgressBar progressBar;
        private BackgroundWorker uninstallWorker;

        public UninstallWizardForm()
        {
            installDir = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');
            LoadBranding();
            InitializeComponent();
            ShowPage(0);
        }

        private void InitializeComponent()
        {
            this.SuspendLayout();

            this.Text = AppName + " Uninstall";
            this.ClientSize = new Size(520, 370);
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = true;
            this.StartPosition = FormStartPosition.CenterScreen;
            this.Font = new Font("Segoe UI", 9F, FontStyle.Regular, GraphicsUnit.Point, ((byte)(0)));

            // Bottom Navigation Panel
            pnlBottom = new Panel
            {
                Dock = DockStyle.Bottom,
                Height = 50,
                BackColor = SystemColors.Control
            };
            pnlBottom.Paint += (s, e) =>
            {
                e.Graphics.DrawLine(new Pen(Color.FromArgb(215, 218, 224)), 0, 0, pnlBottom.Width, 0);
            };

            btnAction = new Button
            {
                Text = "&Uninstall",
                Size = new Size(90, 26),
                Location = new Point(320, 12),
                UseVisualStyleBackColor = true
            };
            btnAction.Click += (s, e) => OnActionClicked();

            btnCancel = new Button
            {
                Text = "Cancel",
                Size = new Size(82, 26),
                Location = new Point(418, 12),
                UseVisualStyleBackColor = true
            };
            btnCancel.Click += (s, e) => this.Close();

            pnlBottom.Controls.Add(btnAction);
            pnlBottom.Controls.Add(btnCancel);
            this.Controls.Add(pnlBottom);

            // Create Pages
            CreateConfirmPage();
            CreateUninstallingPage();
            CreateFinishedPage();

            // BackgroundWorker
            uninstallWorker = new BackgroundWorker();
            uninstallWorker.WorkerReportsProgress = true;
            uninstallWorker.DoWork += UninstallWorker_DoWork;
            uninstallWorker.ProgressChanged += UninstallWorker_ProgressChanged;
            uninstallWorker.RunWorkerCompleted += UninstallWorker_RunWorkerCompleted;

            this.FormClosing += UninstallWizardForm_FormClosing;

            this.ResumeLayout(false);
        }

        private void LoadBranding()
        {
            try
            {
                using (Stream s = Assembly.GetExecutingAssembly().GetManifestResourceStream("logo.png"))
                {
                    if (s != null)
                    {
                        logoImage = Image.FromStream(s);
                    }
                }
            }
            catch { }

            try
            {
                string iconPath = Path.Combine(installDir, "app.ico");
                if (File.Exists(iconPath))
                {
                    appIcon = new Icon(iconPath);
                    this.Icon = appIcon;
                }
                else
                {
                    using (Stream s = Assembly.GetExecutingAssembly().GetManifestResourceStream("app.ico"))
                    {
                        if (s != null)
                        {
                            appIcon = new Icon(s);
                            this.Icon = appIcon;
                        }
                    }
                }
            }
            catch { }

            if (logoImage == null && appIcon != null)
            {
                try { logoImage = appIcon.ToBitmap(); } catch { }
            }
        }

        private Panel CreateHeaderBanner(string title, string subtitle)
        {
            Panel header = new Panel
            {
                Dock = DockStyle.Top,
                Height = 65,
                BackColor = Color.White
            };
            header.Paint += (s, e) =>
            {
                e.Graphics.DrawLine(new Pen(Color.FromArgb(215, 218, 224)), 0, header.Height - 1, header.Width, header.Height - 1);
            };

            Label lblTitle = new Label
            {
                Text = title,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                Location = new Point(24, 13),
                AutoSize = true,
                ForeColor = Color.FromArgb(17, 24, 39)
            };

            Label lblSubtitle = new Label
            {
                Text = subtitle,
                Font = new Font("Segoe UI", 8.5F),
                Location = new Point(38, 34),
                AutoSize = true,
                ForeColor = Color.FromArgb(107, 114, 128)
            };

            PictureBox picLogo = new PictureBox
            {
                Size = new Size(42, 42),
                Location = new Point(header.Width - 62, 11),
                SizeMode = PictureBoxSizeMode.Zoom,
                Anchor = AnchorStyles.Top | AnchorStyles.Right
            };
            if (logoImage != null) picLogo.Image = logoImage;

            header.Controls.Add(lblTitle);
            header.Controls.Add(lblSubtitle);
            header.Controls.Add(picLogo);
            return header;
        }

        #region Page 0: Confirm
        private void CreateConfirmPage()
        {
            pnlConfirm = new Panel
            {
                Dock = DockStyle.Fill,
                Visible = false
            };

            Panel header = CreateHeaderBanner("Uninstall " + AppName, "Remove " + AppName + " from your computer.");
            pnlConfirm.Controls.Add(header);

            Panel body = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = SystemColors.Control,
                Padding = new Padding(32, 20, 32, 16)
            };

            Label lblPrompt = new Label
            {
                Text = "Are you sure you want to completely remove " + AppName + " and all of its components?",
                Location = new Point(32, 20),
                Size = new Size(455, 36),
                Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
                ForeColor = Color.FromArgb(17, 24, 39)
            };

            GroupBox grpLocation = new GroupBox
            {
                Text = "Installation Location",
                Location = new Point(32, 68),
                Size = new Size(455, 68),
                ForeColor = Color.FromArgb(71, 85, 105)
            };

            TextBox txtPath = new TextBox
            {
                Text = installDir,
                Location = new Point(16, 26),
                Size = new Size(420, 23),
                ReadOnly = true,
                BackColor = SystemColors.Window
            };
            grpLocation.Controls.Add(txtPath);

            Label lblWarning = new Label
            {
                Text = "This will terminate any running background tunnels and remove all application files, shortcuts, and configurations from this computer.",
                Location = new Point(32, 150),
                Size = new Size(455, 40),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            body.Controls.Add(lblPrompt);
            body.Controls.Add(grpLocation);
            body.Controls.Add(lblWarning);
            pnlConfirm.Controls.Add(body);

            this.Controls.Add(pnlConfirm);
        }
        #endregion

        #region Page 1: Uninstalling
        private void CreateUninstallingPage()
        {
            pnlUninstalling = new Panel
            {
                Dock = DockStyle.Fill,
                Visible = false
            };

            Panel header = CreateHeaderBanner("Uninstalling " + AppName, "Please wait while " + AppName + " is being removed.");
            pnlUninstalling.Controls.Add(header);

            Panel body = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = SystemColors.Control,
                Padding = new Padding(32, 24, 32, 16)
            };

            lblStatus = new Label
            {
                Text = "Uninstalling " + AppName + "...",
                Location = new Point(32, 24),
                Size = new Size(455, 20),
                Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
                ForeColor = Color.FromArgb(17, 24, 39)
            };

            progressBar = new ProgressBar
            {
                Location = new Point(32, 74),
                Size = new Size(455, 22),
                Minimum = 0,
                Maximum = 100,
                Value = 0,
                Style = ProgressBarStyle.Continuous
            };

            lblDetail = new Label
            {
                Text = "Preparing uninstallation...",
                Location = new Point(32, 110),
                Size = new Size(455, 36),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            body.Controls.Add(lblStatus);
            body.Controls.Add(progressBar);
            body.Controls.Add(lblDetail);
            pnlUninstalling.Controls.Add(body);

            this.Controls.Add(pnlUninstalling);
        }
        #endregion

        #region Page 2: Finished
        private void CreateFinishedPage()
        {
            pnlFinished = new Panel
            {
                Dock = DockStyle.Fill,
                Visible = false
            };

            Panel header = CreateHeaderBanner("Uninstall Complete", AppName + " has been successfully removed.");
            pnlFinished.Controls.Add(header);

            Panel body = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = SystemColors.Control,
                Padding = new Padding(32, 28, 32, 16)
            };

            Label lblMessage = new Label
            {
                Text = AppName + " was successfully removed from your computer.\n\nAll application files, shortcuts, and background services have been deleted.",
                Location = new Point(32, 35),
                Size = new Size(455, 60),
                Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
                ForeColor = Color.FromArgb(30, 41, 59)
            };

            Label lblClickFinish = new Label
            {
                Text = "Click Finish to exit this wizard.",
                Location = new Point(32, 115),
                Size = new Size(455, 20),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            body.Controls.Add(lblMessage);
            body.Controls.Add(lblClickFinish);
            pnlFinished.Controls.Add(body);

            this.Controls.Add(pnlFinished);
        }
        #endregion

        private void ShowPage(int pageIndex)
        {
            currentPage = pageIndex;
            pnlConfirm.Visible = (pageIndex == 0);
            pnlUninstalling.Visible = (pageIndex == 1);
            pnlFinished.Visible = (pageIndex == 2);

            if (pageIndex == 0)
            {
                btnAction.Text = "&Uninstall";
                btnAction.Enabled = true;
                btnCancel.Visible = true;
                btnCancel.Enabled = true;
            }
            else if (pageIndex == 1)
            {
                btnAction.Enabled = false;
                btnCancel.Enabled = false;
            }
            else if (pageIndex == 2)
            {
                btnAction.Text = "&Finish";
                btnAction.Enabled = true;
                btnCancel.Visible = false;
                btnAction.Focus();
            }
        }

        private void OnActionClicked()
        {
            if (currentPage == 0)
            {
                ShowPage(1);
                uninstallWorker.RunWorkerAsync();
            }
            else if (currentPage == 2)
            {
                this.Close();
            }
        }

        private void UninstallWizardForm_FormClosing(object sender, FormClosingEventArgs e)
        {
            if (currentPage == 1 && uninstallWorker.IsBusy)
            {
                e.Cancel = true;
                MessageBox.Show(
                    "Please wait until the uninstallation process is complete.",
                    "Uninstall in Progress",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information
                );
            }
            else if (currentPage == 2)
            {
                // Trigger detached self-deletion script on exit
                UninstallEngine.SpawnSelfDeleteScript(installDir);
            }
        }

        private void UninstallWorker_DoWork(object sender, DoWorkEventArgs e)
        {
            UninstallEngine engine = new UninstallEngine(installDir);
            engine.Execute(uninstallWorker);
        }

        private void UninstallWorker_ProgressChanged(object sender, ProgressChangedEventArgs e)
        {
            progressBar.Value = Math.Min(100, Math.Max(0, e.ProgressPercentage));
            if (e.UserState != null)
            {
                string msg = e.UserState.ToString();
                if (msg.Length > 65)
                {
                    msg = "..." + msg.Substring(msg.Length - 62);
                }
                lblDetail.Text = msg;
            }
        }

        private void UninstallWorker_RunWorkerCompleted(object sender, RunWorkerCompletedEventArgs e)
        {
            ShowPage(2);
        }
    }

    public class UninstallEngine
    {
        private const string AppName = "TunnelDeck";
        private const string AppRegKey = "TunnelDeck";
        private string installDir;

        public UninstallEngine(string installDir)
        {
            this.installDir = installDir;
        }

        public void Execute(BackgroundWorker worker)
        {
            Report(worker, 5, "Terminating running background processes...");
            KillProcess("App");
            KillProcess("cloudflared");
            KillProcess("php");

            // 1. Remove Desktop Shortcuts
            Report(worker, 20, "Removing Desktop shortcuts...");
            RemoveShortcut(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "TunnelDeck.lnk");
            RemoveShortcut(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "TunnelDeck Lite.lnk");
            RemoveShortcut(Environment.GetFolderPath(Environment.SpecialFolder.CommonDesktopDirectory), "TunnelDeck.lnk");
            RemoveShortcut(Environment.GetFolderPath(Environment.SpecialFolder.CommonDesktopDirectory), "TunnelDeck Lite.lnk");

            // 2. Remove Start Menu Shortcuts
            Report(worker, 35, "Removing Start Menu shortcuts...");
            RemoveDirectorySafe(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.StartMenu), "Programs", "TunnelDeck"));
            RemoveDirectorySafe(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.StartMenu), "Programs", "TunnelDeck Lite"));
            RemoveDirectorySafe(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonStartMenu), "Programs", "TunnelDeck"));
            RemoveDirectorySafe(Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonStartMenu), "Programs", "TunnelDeck Lite"));

            // 3. Remove Windows Registry Entries
            Report(worker, 50, "Cleaning up Windows registry entries...");
            DeleteRegistrySubKeyTree(Registry.CurrentUser, @"Software\Microsoft\Windows\CurrentVersion\Uninstall", "TunnelDeck");
            DeleteRegistrySubKeyTree(Registry.CurrentUser, @"Software\Microsoft\Windows\CurrentVersion\Uninstall", "TunnelDeckLite");
            DeleteRegistrySubKeyTree(Registry.LocalMachine, @"Software\Microsoft\Windows\CurrentVersion\Uninstall", "TunnelDeck");
            DeleteRegistrySubKeyTree(Registry.LocalMachine, @"Software\Microsoft\Windows\CurrentVersion\Uninstall", "TunnelDeckLite");
            DeleteRegistryValue(Registry.CurrentUser, @"Software\Microsoft\Windows\CurrentVersion\Run", "TunnelDeck");
            DeleteRegistryValue(Registry.CurrentUser, @"Software\Microsoft\Windows\CurrentVersion\Run", "TunnelDeckLite");
            DeleteRegistryValue(Registry.LocalMachine, @"Software\Microsoft\Windows\CurrentVersion\Run", "TunnelDeck");
            DeleteRegistryValue(Registry.LocalMachine, @"Software\Microsoft\Windows\CurrentVersion\Run", "TunnelDeckLite");

            // 4. Remove Files and Folders in InstallDir
            Report(worker, 65, "Removing installed files...");
            string currentExe = Process.GetCurrentProcess().MainModule.FileName;

            // Safeguard against deleting source development repository
            if (File.Exists(Path.Combine(installDir, ".git")) ||
                Directory.Exists(Path.Combine(installDir, ".git")) ||
                Directory.Exists(Path.Combine(installDir, "win\\.builder")))
            {
                Report(worker, 100, "Skipped deleting active development repository.");
                return;
            }

            if (Directory.Exists(installDir))
            {
                try
                {
                    string[] allFiles = Directory.GetFiles(installDir, "*", SearchOption.AllDirectories);
                    int total = allFiles.Length;
                    int count = 0;

                    foreach (string file in allFiles)
                    {
                        count++;
                        int pct = 65 + (int)(((double)count / Math.Max(1, total)) * 30.0);
                        if (!file.Equals(currentExe, StringComparison.OrdinalIgnoreCase))
                        {
                            try
                            {
                                File.SetAttributes(file, FileAttributes.Normal);
                                File.Delete(file);
                            }
                            catch { }
                        }
                        if (count % 10 == 0)
                        {
                            Report(worker, pct, "Deleting: " + Path.GetFileName(file));
                        }
                    }

                    // Remove empty directories from bottom up
                    string[] allDirs = Directory.GetDirectories(installDir, "*", SearchOption.AllDirectories);
                    Array.Sort(allDirs, (a, b) => b.Length.CompareTo(a.Length));
                    foreach (string d in allDirs)
                    {
                        try { Directory.Delete(d, true); } catch { }
                    }
                }
                catch { }
            }

            Report(worker, 100, "Uninstallation completed.");
            Thread.Sleep(300);
        }

        public static void SpawnSelfDeleteScript(string installDir)
        {
            try
            {
                if (File.Exists(Path.Combine(installDir, ".git")) ||
                    Directory.Exists(Path.Combine(installDir, ".git")) ||
                    Directory.Exists(Path.Combine(installDir, "win\\.builder")))
                {
                    return;
                }

                string currentExe = Process.GetCurrentProcess().MainModule.FileName;
                string tempBatch = Path.Combine(Path.GetTempPath(), "td_cleanup_" + Guid.NewGuid().ToString("N") + ".bat");

                string script = string.Format(
                    "@echo off\r\n" +
                    "ping 127.0.0.1 -n 2 > nul\r\n" +
                    ":retry\r\n" +
                    "del /f /q \"{0}\" > nul 2>&1\r\n" +
                    "if exist \"{0}\" (\r\n" +
                    "    ping 127.0.0.1 -n 1 > nul\r\n" +
                    "    goto retry\r\n" +
                    ")\r\n" +
                    "rmdir /s /q \"{1}\" > nul 2>&1\r\n" +
                    "del \"%~f0\" > nul 2>&1\r\n",
                    currentExe,
                    installDir
                );

                File.WriteAllText(tempBatch, script);

                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = "cmd.exe",
                    Arguments = "/C \"" + tempBatch + "\"",
                    WindowStyle = ProcessWindowStyle.Hidden,
                    CreateNoWindow = true,
                    UseShellExecute = true
                };
                Process.Start(psi);
            }
            catch { }
        }

        private void Report(BackgroundWorker worker, int progress, string message)
        {
            if (worker != null && worker.WorkerReportsProgress)
            {
                worker.ReportProgress(progress, message);
            }
        }

        private static void KillProcess(string name)
        {
            try
            {
                foreach (Process p in Process.GetProcessesByName(name))
                {
                    try
                    {
                        p.Kill();
                        p.WaitForExit(1500);
                    }
                    catch { }
                }
            }
            catch { }
        }

        private static void RemoveShortcut(string dir, string name)
        {
            try
            {
                if (string.IsNullOrEmpty(dir)) return;
                string path = Path.Combine(dir, name);
                if (File.Exists(path))
                {
                    File.Delete(path);
                }
            }
            catch { }
        }

        private static void RemoveDirectorySafe(string dir)
        {
            try
            {
                if (Directory.Exists(dir))
                {
                    Directory.Delete(dir, true);
                }
            }
            catch { }
        }

        private static void DeleteRegistrySubKeyTree(RegistryKey root, string subKey, string targetKey)
        {
            try
            {
                using (RegistryKey parent = root.OpenSubKey(subKey, true))
                {
                    if (parent != null)
                    {
                        parent.DeleteSubKeyTree(targetKey, false);
                    }
                }
            }
            catch { }
        }

        private static void DeleteRegistryValue(RegistryKey root, string subKey, string valueName)
        {
            try
            {
                using (RegistryKey parent = root.OpenSubKey(subKey, true))
                {
                    if (parent != null)
                    {
                        parent.DeleteValue(valueName, false);
                    }
                }
            }
            catch { }
        }
    }
}
