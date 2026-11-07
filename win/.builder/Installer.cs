using System;
using System.ComponentModel;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.IO;
using System.IO.Compression;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace TunnelDeck.Installer
{
    public class DoubleBufferedPanel : Panel
    {
        public DoubleBufferedPanel()
        {
            this.DoubleBuffered = true;
            this.SetStyle(ControlStyles.AllPaintingInWmPaint | ControlStyles.UserPaint | ControlStyles.OptimizedDoubleBuffer | ControlStyles.ResizeRedraw, true);
            this.UpdateStyles();
        }
    }

    public class SetupWizardForm : Form
    {
        // Pages
        private int currentPage = 0;
        private Panel pnlWelcome;
        private Panel pnlDestination;
        private Panel pnlTasks;
        private Panel pnlInstalling;
        private Panel pnlFinished;

        // Bottom Controls
        private Panel pnlBottom;
        private Button btnBack;
        private Button btnNext;
        private Button btnCancel;

        // Destination Controls
        private TextBox txtInstallPath;
        private Button btnBrowse;
        private Label lblSpaceRequired;
        private Label lblSpaceAvailable;

        // Task Controls
        private CheckBox chkDesktopShortcut;
        private CheckBox chkStartMenuShortcut;
        private CheckBox chkAutoStart;

        // Installing Controls
        private Label lblInstallStatus;
        private Label lblCurrentFile;
        private ProgressBar progressBar;
        private BackgroundWorker installWorker;
        private DoubleBufferedPanel pnlIntelligence;
        private System.Windows.Forms.Timer tipTimer;
        private int currentTipIndex = 0;
        private int lastReportedPercent = -1;

        private class IntelligenceTip
        {
            public string Category { get; set; }
            public string Title { get; set; }
            public string Description { get; set; }
            public string TechDetail { get; set; }

            public IntelligenceTip(string category, string title, string description, string techDetail)
            {
                Category = category;
                Title = title;
                Description = description;
                TechDetail = techDetail;
            }
        }

        private readonly IntelligenceTip[] intelligenceTips = new IntelligenceTip[]
        {
            new IntelligenceTip(
                "GLOBAL EDGE ROUTING",
                "Cloudflare Anycast Global Routing",
                "TunnelDeck establishes multiplexed tunnels directly into Cloudflare's Anycast edge across 300+ cities worldwide, eliminating complex port forwarding or router setup.",
                "Zero NAT configuration • Sub-50ms worldwide edge response"
            ),
            new IntelligenceTip(
                "INTELLIGENT TELEMETRY",
                "Real-Time Request & Webhook Inspector",
                "Live telemetry captures incoming HTTP headers, payloads, query parameters, and response status codes with instant 1-click webhook replaying.",
                "Accelerates debugging for Stripe, GitHub & custom webhooks"
            ),
            new IntelligenceTip(
                "SELF-HEALING DAEMON",
                "Automated Tunnel & Health Supervisor",
                "Built-in health supervisor continuously monitors daemon heartbeats and connection stability, performing silent, zero-drop reconnects when network state changes.",
                "Sub-second fault recovery with background auto-reconnect"
            ),
            new IntelligenceTip(
                "ZERO DEPENDENCY RUNTIME",
                "Self-Contained Runtime & SQLite Core",
                "Includes an embedded micro-runtime and transactional SQLite database engine with zero external dependencies, web server installations, or runtime bloat.",
                "100% portable runtime isolated inside application directory"
            ),
            new IntelligenceTip(
                "MULTI-ENVIRONMENT BRIDGES",
                "Universal Localhost, Docker & WSL Support",
                "Seamlessly bridge any local port, microservice, or Docker container to an authenticated public HTTPS URL with automated SSL certificate provisioning.",
                "Instant HTTPS endpoints with automatic SSL termination"
            )
        };

        // Finished Controls
        private CheckBox chkLaunchApp;

        // App Branding
        private const string AppName = "TunnelDeck";
        private const string AppVersion = "1.0.0";
        private const string AppPublisher = "TunnelDeck";
        private const string AppRegKey = "TunnelDeck";
        private Icon appIcon;
        private Image logoImage;

        public SetupWizardForm()
        {
            LoadBranding();
            InitializeComponent();
            ShowPage(0);
        }

        private void InitializeComponent()
        {
            this.SuspendLayout();

            this.Text = "Setup - " + AppName;
            this.ClientSize = new Size(540, 410);
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

            btnBack = new Button
            {
                Text = "< &Back",
                Size = new Size(82, 26),
                Location = new Point(275, 12),
                UseVisualStyleBackColor = true
            };
            btnBack.Click += (s, e) => OnBackClicked();

            btnNext = new Button
            {
                Text = "&Next >",
                Size = new Size(82, 26),
                Location = new Point(362, 12),
                UseVisualStyleBackColor = true
            };
            btnNext.Click += (s, e) => OnNextClicked();

            btnCancel = new Button
            {
                Text = "Cancel",
                Size = new Size(82, 26),
                Location = new Point(449, 12),
                UseVisualStyleBackColor = true
            };
            btnCancel.Click += (s, e) => OnCancelClicked();

            pnlBottom.Controls.Add(btnBack);
            pnlBottom.Controls.Add(btnNext);
            pnlBottom.Controls.Add(btnCancel);
            this.Controls.Add(pnlBottom);

            // Create Wizard Pages
            CreateWelcomePage();
            CreateDestinationPage();
            CreateTasksPage();
            CreateInstallingPage();
            CreateFinishedPage();

            // BackgroundWorker for non-blocking file extraction
            installWorker = new BackgroundWorker();
            installWorker.WorkerReportsProgress = true;
            installWorker.DoWork += InstallWorker_DoWork;
            installWorker.ProgressChanged += InstallWorker_ProgressChanged;
            installWorker.RunWorkerCompleted += InstallWorker_RunWorkerCompleted;

            this.FormClosing += SetupWizardForm_FormClosing;

            this.ResumeLayout(false);
        }

        private void LoadBranding()
        {
            try
            {
                // 1. High-resolution branding logo
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
                // 2. Window and taskbar icon
                using (Stream s = Assembly.GetExecutingAssembly().GetManifestResourceStream("app.ico"))
                {
                    if (s != null)
                    {
                        appIcon = new Icon(s);
                        this.Icon = appIcon;
                    }
                }
            }
            catch
            {
                try
                {
                    appIcon = Icon.ExtractAssociatedIcon(Application.ExecutablePath);
                    if (appIcon != null) this.Icon = appIcon;
                }
                catch { }
            }

            if (logoImage == null && appIcon != null)
            {
                try { logoImage = appIcon.ToBitmap(); } catch { }
            }
        }

        #region Page 1: Welcome
        private void CreateWelcomePage()
        {
            pnlWelcome = new Panel { Dock = DockStyle.Fill, Visible = false };

            // Left Banner Panel with Gradient
            Panel pnlBanner = new Panel
            {
                Dock = DockStyle.Left,
                Width = 165
            };
            pnlBanner.Paint += (s, e) =>
            {
                using (LinearGradientBrush brush = new LinearGradientBrush(
                    pnlBanner.ClientRectangle,
                    Color.FromArgb(16, 0, 145),      // TunnelDeck Brand Blue
                    Color.FromArgb(30, 27, 75),      // Deep Indigo
                    LinearGradientMode.Vertical))
                {
                    e.Graphics.FillRectangle(brush, pnlBanner.ClientRectangle);
                }

                e.Graphics.SmoothingMode = SmoothingMode.HighQuality;
                e.Graphics.InterpolationMode = InterpolationMode.HighQualityBicubic;
                e.Graphics.PixelOffsetMode = PixelOffsetMode.HighQuality;

                // Draw App Icon
                if (logoImage != null)
                {
                    e.Graphics.DrawImage(logoImage, new Rectangle(24, 28, 58, 58));
                }

                // Draw App Title in White
                using (Font f1 = new Font("Segoe UI", 12F, FontStyle.Bold))
                using (Font f2 = new Font("Segoe UI", 8.5F, FontStyle.Regular))
                using (Brush b = new SolidBrush(Color.White))
                using (Brush bSub = new SolidBrush(Color.FromArgb(200, 210, 255)))
                {
                    e.Graphics.DrawString("TunnelDeck", f1, b, 24, 98);
                    e.Graphics.DrawString("v" + AppVersion, f2, bSub, 24, 122);
                }
            };

            // Content Area
            Panel pnlContent = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = Color.White,
                Padding = new Padding(24, 28, 24, 20)
            };

            Label lblTitle = new Label
            {
                Text = "Welcome to the " + AppName + " Setup Wizard",
                Font = new Font("Segoe UI", 12F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                Dock = DockStyle.Top,
                Height = 52
            };

            Label lblBody = new Label
            {
                Text = "This wizard will guide you through installing " + AppName + " on your computer.\n\n" +
                       AppName + " provides instant, lightning-fast public HTTPS tunnels for your local web development servers using secure Cloudflare edge routing.\n\n" +
                       "• Zero configuration required — portable runtime engine included\n" +
                       "• Built-in SQLite database and management UI\n" +
                       "• Works with any local HTTP server (localhost, Docker, WSL)\n\n" +
                       "Click Next to continue, or Cancel to exit Setup.",
                Font = new Font("Segoe UI", 9F, FontStyle.Regular),
                ForeColor = Color.FromArgb(71, 85, 105),
                Dock = DockStyle.Fill
            };

            pnlContent.Controls.Add(lblBody);
            pnlContent.Controls.Add(lblTitle);

            pnlWelcome.Controls.Add(pnlContent);
            pnlWelcome.Controls.Add(pnlBanner);
            this.Controls.Add(pnlWelcome);
        }
        #endregion

        #region Page 2: Destination Location
        private void CreateDestinationPage()
        {
            pnlDestination = new Panel { Dock = DockStyle.Fill, Visible = false };

            Panel pnlHeader = CreateHeaderBanner("Select Destination Location", "Where should " + AppName + " be installed?");

            Panel pnlBody = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = SystemColors.Control,
                Padding = new Padding(32, 16, 32, 16)
            };

            Label lblDesc = new Label
            {
                Text = "Setup will install " + AppName + " into the following folder.\n\n" +
                       "To continue, click Next. If you would like to select a different folder, click Browse.",
                Location = new Point(32, 16),
                Size = new Size(470, 48),
                ForeColor = Color.FromArgb(51, 65, 85)
            };

            GroupBox grpPath = new GroupBox
            {
                Text = "Destination Folder",
                Location = new Point(32, 70),
                Size = new Size(475, 75),
                ForeColor = Color.FromArgb(71, 85, 105)
            };

            string programFiles = Environment.GetEnvironmentVariable("ProgramW6432");
            if (string.IsNullOrEmpty(programFiles))
            {
                programFiles = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);
            }
            string defaultDir = Path.Combine(programFiles, AppName);

            txtInstallPath = new TextBox
            {
                Location = new Point(16, 30),
                Size = new Size(355, 23),
                Text = defaultDir
            };
            txtInstallPath.TextChanged += (s, e) => UpdateDiskSpaceInfo();

            btnBrowse = new Button
            {
                Text = "B&rowse...",
                Location = new Point(380, 29),
                Size = new Size(80, 25),
                UseVisualStyleBackColor = true
            };
            btnBrowse.Click += (s, e) =>
            {
                using (FolderBrowserDialog fbd = new FolderBrowserDialog())
                {
                    fbd.Description = "Select the folder in which to install " + AppName + ":";
                    fbd.SelectedPath = txtInstallPath.Text;
                    fbd.ShowNewFolderButton = true;
                    if (fbd.ShowDialog(this) == DialogResult.OK)
                    {
                        txtInstallPath.Text = fbd.SelectedPath;
                    }
                }
            };

            grpPath.Controls.Add(txtInstallPath);
            grpPath.Controls.Add(btnBrowse);

            lblSpaceRequired = new Label
            {
                Text = "At least 120.0 MB of free disk space is required.",
                Location = new Point(32, 165),
                Size = new Size(470, 20),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            lblSpaceAvailable = new Label
            {
                Text = "Available space: Calculating...",
                Location = new Point(32, 188),
                Size = new Size(470, 20),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            pnlBody.Controls.Add(lblDesc);
            pnlBody.Controls.Add(grpPath);
            pnlBody.Controls.Add(lblSpaceRequired);
            pnlBody.Controls.Add(lblSpaceAvailable);

            pnlDestination.Controls.Add(pnlBody);
            pnlDestination.Controls.Add(pnlHeader);
            this.Controls.Add(pnlDestination);

            UpdateDiskSpaceInfo();
        }

        private void UpdateDiskSpaceInfo()
        {
            try
            {
                string path = txtInstallPath.Text;
                string root = Path.GetPathRoot(path);
                if (!string.IsNullOrEmpty(root))
                {
                    DriveInfo drive = new DriveInfo(root);
                    if (drive.IsReady)
                    {
                        double freeGb = Math.Round((double)drive.AvailableFreeSpace / (1024 * 1024 * 1024), 1);
                        lblSpaceAvailable.Text = string.Format("Available space on drive {0}: {1} GB", root.TrimEnd('\\'), freeGb);
                        return;
                    }
                }
            }
            catch { }
            lblSpaceAvailable.Text = "Available space: Sufficient";
        }
        #endregion

        #region Page 3: Additional Tasks
        private void CreateTasksPage()
        {
            pnlTasks = new Panel { Dock = DockStyle.Fill, Visible = false };

            Panel pnlHeader = CreateHeaderBanner("Select Additional Tasks", "Which additional shortcuts should be created?");

            Panel pnlBody = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = SystemColors.Control,
                Padding = new Padding(32, 16, 32, 16)
            };

            Label lblDesc = new Label
            {
                Text = "Select the additional tasks you would like Setup to perform while installing " + AppName + ", then click Next.",
                Location = new Point(32, 16),
                Size = new Size(470, 36),
                ForeColor = Color.FromArgb(51, 65, 85)
            };

            GroupBox grpShortcuts = new GroupBox
            {
                Text = "Shortcuts and Integration",
                Location = new Point(32, 60),
                Size = new Size(475, 125),
                ForeColor = Color.FromArgb(71, 85, 105)
            };

            chkDesktopShortcut = new CheckBox
            {
                Text = "Create a &desktop shortcut",
                Checked = true,
                Location = new Point(20, 28),
                Size = new Size(400, 24),
                ForeColor = Color.FromArgb(30, 41, 59)
            };

            chkStartMenuShortcut = new CheckBox
            {
                Text = "Create a &Start Menu program shortcut",
                Checked = true,
                Location = new Point(20, 58),
                Size = new Size(400, 24),
                ForeColor = Color.FromArgb(30, 41, 59)
            };

            chkAutoStart = new CheckBox
            {
                Text = "Automatically start " + AppName + " when logging into Windows",
                Checked = false,
                Location = new Point(20, 88),
                Size = new Size(440, 24),
                ForeColor = Color.FromArgb(30, 41, 59)
            };

            grpShortcuts.Controls.Add(chkDesktopShortcut);
            grpShortcuts.Controls.Add(chkStartMenuShortcut);
            grpShortcuts.Controls.Add(chkAutoStart);

            pnlBody.Controls.Add(lblDesc);
            pnlBody.Controls.Add(grpShortcuts);

            pnlTasks.Controls.Add(pnlBody);
            pnlTasks.Controls.Add(pnlHeader);
            this.Controls.Add(pnlTasks);
        }
        #endregion

        #region Page 4: Installing
        private void CreateInstallingPage()
        {
            pnlInstalling = new Panel { Dock = DockStyle.Fill, Visible = false };

            Panel pnlHeader = CreateHeaderBanner("Installing", "Please wait while Setup installs " + AppName + " on your computer.");

            Panel pnlBody = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = SystemColors.Control,
                Padding = new Padding(32, 16, 32, 16)
            };

            lblInstallStatus = new Label
            {
                Text = "Extracting files...",
                Location = new Point(32, 14),
                Size = new Size(475, 18),
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                ForeColor = Color.FromArgb(30, 41, 59)
            };

            progressBar = new ProgressBar
            {
                Location = new Point(32, 38),
                Size = new Size(475, 22),
                Style = ProgressBarStyle.Continuous,
                Minimum = 0,
                Maximum = 100,
                Value = 0
            };

            lblCurrentFile = new Label
            {
                Text = "Preparing installation payload...",
                Location = new Point(32, 66),
                Size = new Size(475, 18),
                Font = new Font("Segoe UI", 8.25F, FontStyle.Regular),
                ForeColor = Color.FromArgb(100, 116, 139)
            };

            // Intelligence Information Box with Blue-themed background
            pnlIntelligence = new DoubleBufferedPanel
            {
                Location = new Point(32, 92),
                Size = new Size(475, 155),
                Cursor = Cursors.Hand
            };
            pnlIntelligence.Paint += PnlIntelligence_Paint;
            pnlIntelligence.Click += (s, e) =>
            {
                currentTipIndex = (currentTipIndex + 1) % intelligenceTips.Length;
                pnlIntelligence.Invalidate();
            };

            pnlBody.Controls.Add(lblInstallStatus);
            pnlBody.Controls.Add(progressBar);
            pnlBody.Controls.Add(lblCurrentFile);
            pnlBody.Controls.Add(pnlIntelligence);

            // Add pnlBody first, then pnlHeader so pnlHeader docks cleanly at Y=0 and pnlBody starts at Y=65
            pnlInstalling.Controls.Add(pnlBody);
            pnlInstalling.Controls.Add(pnlHeader);
            this.Controls.Add(pnlInstalling);

            // Timer for rotating intelligence cards
            tipTimer = new System.Windows.Forms.Timer();
            tipTimer.Interval = 3200; // 3.2 seconds
            tipTimer.Tick += (s, e) =>
            {
                currentTipIndex = (currentTipIndex + 1) % intelligenceTips.Length;
                if (pnlIntelligence != null && !pnlIntelligence.IsDisposed)
                {
                    pnlIntelligence.Invalidate();
                }
            };
        }

        private void PnlIntelligence_Paint(object sender, PaintEventArgs e)
        {
            Graphics g = e.Graphics;
            g.SmoothingMode = SmoothingMode.AntiAlias;
            g.TextRenderingHint = System.Drawing.Text.TextRenderingHint.ClearTypeGridFit;

            Rectangle rect = new Rectangle(0, 0, pnlIntelligence.Width - 1, pnlIntelligence.Height - 1);

            // 1. Blue gradient background
            using (GraphicsPath path = GetRoundedRectangle(rect, 8))
            {
                using (LinearGradientBrush bgBrush = new LinearGradientBrush(
                    rect,
                    Color.FromArgb(10, 20, 64),      // Deep Tech Blue
                    Color.FromArgb(24, 18, 86),      // Rich Indigo
                    LinearGradientMode.ForwardDiagonal))
                {
                    g.FillPath(bgBrush, path);
                }

                // Glowing blue border
                using (Pen borderPen = new Pen(Color.FromArgb(90, 59, 130, 246), 1.25f))
                {
                    g.DrawPath(borderPen, path);
                }
            }

            if (intelligenceTips == null || intelligenceTips.Length == 0) return;
            IntelligenceTip tip = intelligenceTips[currentTipIndex % intelligenceTips.Length];

            // 2. Intelligence Badge (Pill)
            string badgeText = "⚡  " + tip.Category;
            using (Font fontBadge = new Font("Segoe UI", 7.5F, FontStyle.Bold))
            {
                SizeF badgeSize = g.MeasureString(badgeText, fontBadge);
                Rectangle badgeRect = new Rectangle(16, 14, (int)badgeSize.Width + 14, 20);

                using (GraphicsPath badgePath = GetRoundedRectangle(badgeRect, 4))
                {
                    using (SolidBrush badgeBg = new SolidBrush(Color.FromArgb(45, 56, 189, 248))) // semi-transparent cyan
                    {
                        g.FillPath(badgeBg, badgePath);
                    }
                    using (Pen badgeBorder = new Pen(Color.FromArgb(110, 56, 189, 248), 1f))
                    {
                        g.DrawPath(badgeBorder, badgePath);
                    }
                }

                using (Brush badgeTextBrush = new SolidBrush(Color.FromArgb(186, 230, 253))) // Light cyan text
                {
                    g.DrawString(badgeText, fontBadge, badgeTextBrush, badgeRect.X + 7, badgeRect.Y + 3);
                }
            }

            // Tag on the top right
            string indexText = string.Format("FEATURE {0} / {1}", (currentTipIndex % intelligenceTips.Length) + 1, intelligenceTips.Length);
            using (Font fontIdx = new Font("Segoe UI", 7.5F, FontStyle.Bold))
            using (Brush brushIdx = new SolidBrush(Color.FromArgb(130, 150, 195)))
            {
                SizeF idxSize = g.MeasureString(indexText, fontIdx);
                g.DrawString(indexText, fontIdx, brushIdx, pnlIntelligence.Width - idxSize.Width - 18, 17);
            }

            // 3. Feature Title in Bold White
            using (Font fontTitle = new Font("Segoe UI", 10.5F, FontStyle.Bold))
            using (Brush brushTitle = new SolidBrush(Color.White))
            {
                g.DrawString(tip.Title, fontTitle, brushTitle, 16, 42);
            }

            // 4. Description in Soft Indigo-White
            using (Font fontDesc = new Font("Segoe UI", 8.5F, FontStyle.Regular))
            using (Brush brushDesc = new SolidBrush(Color.FromArgb(224, 231, 255)))
            using (StringFormat sf = new StringFormat { Trimming = StringTrimming.EllipsisWord })
            {
                Rectangle descRect = new Rectangle(16, 68, pnlIntelligence.Width - 32, 42);
                g.DrawString(tip.Description, fontDesc, brushDesc, descRect, sf);
            }

            // 5. Tech Detail highlight line
            using (Font fontDetail = new Font("Segoe UI", 8F, FontStyle.Regular))
            using (Brush brushDetail = new SolidBrush(Color.FromArgb(96, 165, 250))) // Sky blue
            {
                g.DrawString("✦  " + tip.TechDetail, fontDetail, brushDetail, 16, 116);
            }

            // 6. Carousel Indicator Dots at bottom
            int dotY = 144;
            int dotStartX = 16;
            for (int i = 0; i < intelligenceTips.Length; i++)
            {
                if (i == (currentTipIndex % intelligenceTips.Length))
                {
                    // Active indicator: smooth pill
                    Rectangle activeDot = new Rectangle(dotStartX, dotY, 20, 6);
                    using (GraphicsPath dotPath = GetRoundedRectangle(activeDot, 3))
                    using (SolidBrush dotBrush = new SolidBrush(Color.FromArgb(56, 189, 248))) // Bright cyan
                    {
                        g.FillPath(dotBrush, dotPath);
                    }
                    dotStartX += 26;
                }
                else
                {
                    // Inactive dot
                    using (SolidBrush dotBrush = new SolidBrush(Color.FromArgb(60, 85, 135)))
                    {
                        g.FillEllipse(dotBrush, dotStartX, dotY, 6, 6);
                    }
                    dotStartX += 12;
                }
            }

            // Hint to user: click card to cycle
            using (Font fontHint = new Font("Segoe UI", 7F, FontStyle.Regular))
            using (Brush brushHint = new SolidBrush(Color.FromArgb(95, 115, 155)))
            {
                string hintText = "Click card to view next highlight";
                SizeF hintSize = g.MeasureString(hintText, fontHint);
                g.DrawString(hintText, fontHint, brushHint, pnlIntelligence.Width - hintSize.Width - 16, 142);
            }
        }

        private static GraphicsPath GetRoundedRectangle(Rectangle bounds, int radius)
        {
            GraphicsPath path = new GraphicsPath();
            if (radius <= 0)
            {
                path.AddRectangle(bounds);
                return path;
            }

            int diameter = radius * 2;
            if (diameter > bounds.Width) diameter = bounds.Width;
            if (diameter > bounds.Height) diameter = bounds.Height;

            Rectangle arc = new Rectangle(bounds.X, bounds.Y, diameter, diameter);

            // Top-left
            path.AddArc(arc, 180, 90);

            // Top-right
            arc.X = bounds.Right - diameter;
            path.AddArc(arc, 270, 90);

            // Bottom-right
            arc.Y = bounds.Bottom - diameter;
            path.AddArc(arc, 0, 90);

            // Bottom-left
            arc.X = bounds.Left;
            path.AddArc(arc, 90, 90);

            path.CloseFigure();
            return path;
        }
        #endregion

        #region Page 5: Finished
        private void CreateFinishedPage()
        {
            pnlFinished = new Panel { Dock = DockStyle.Fill, Visible = false };

            // Left Banner Panel
            Panel pnlBanner = new Panel
            {
                Dock = DockStyle.Left,
                Width = 165
            };
            pnlBanner.Paint += (s, e) =>
            {
                using (LinearGradientBrush brush = new LinearGradientBrush(
                    pnlBanner.ClientRectangle,
                    Color.FromArgb(16, 0, 145),
                    Color.FromArgb(30, 27, 75),
                    LinearGradientMode.Vertical))
                {
                    e.Graphics.FillRectangle(brush, pnlBanner.ClientRectangle);
                }

                if (logoImage != null)
                {
                    e.Graphics.DrawImage(logoImage, new Rectangle(24, 32, 54, 54));
                }

                using (Font f1 = new Font("Segoe UI", 12F, FontStyle.Bold))
                using (Font f2 = new Font("Segoe UI", 8.5F, FontStyle.Regular))
                using (Brush b = new SolidBrush(Color.White))
                using (Brush bSub = new SolidBrush(Color.FromArgb(200, 210, 255)))
                {
                    e.Graphics.DrawString("TunnelDeck", f1, b, 24, 96);
                    e.Graphics.DrawString("Installation Complete", f2, bSub, 24, 120);
                }
            };

            // Content Area
            Panel pnlContent = new Panel
            {
                Dock = DockStyle.Fill,
                BackColor = Color.White,
                Padding = new Padding(24, 28, 24, 20)
            };

            Label lblTitle = new Label
            {
                Text = "Completing the " + AppName + " Setup Wizard",
                Font = new Font("Segoe UI", 12F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                Dock = DockStyle.Top,
                Height = 52
            };

            Label lblBody = new Label
            {
                Text = "Setup has finished installing " + AppName + " on your computer.\n\n" +
                       "The application may be launched by selecting the installed shortcuts or from your desktop.\n\n" +
                       "Click Finish to exit Setup.",
                Font = new Font("Segoe UI", 9F, FontStyle.Regular),
                ForeColor = Color.FromArgb(71, 85, 105),
                Dock = DockStyle.Top,
                Height = 120
            };

            chkLaunchApp = new CheckBox
            {
                Text = "Launch " + AppName + " now",
                Checked = true,
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                ForeColor = Color.FromArgb(16, 0, 145),
                Dock = DockStyle.Top,
                Height = 36
            };

            pnlContent.Controls.Add(chkLaunchApp);
            pnlContent.Controls.Add(lblBody);
            pnlContent.Controls.Add(lblTitle);

            pnlFinished.Controls.Add(pnlContent);
            pnlFinished.Controls.Add(pnlBanner);
            this.Controls.Add(pnlFinished);
        }
        #endregion

        #region Helper: Standard Header Banner
        private Panel CreateHeaderBanner(string title, string subtitle)
        {
            Panel pnl = new Panel
            {
                Dock = DockStyle.Top,
                Height = 65,
                BackColor = Color.White
            };

            pnl.Paint += (s, e) =>
            {
                e.Graphics.DrawLine(new Pen(Color.FromArgb(220, 224, 230)), 0, pnl.Height - 1, pnl.Width, pnl.Height - 1);
                if (logoImage != null)
                {
                    e.Graphics.DrawImage(logoImage, new Rectangle(pnl.Width - 52, 14, 36, 36));
                }
            };

            Label lblTitle = new Label
            {
                Text = title,
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                Location = new Point(20, 12),
                AutoSize = true
            };

            Label lblSub = new Label
            {
                Text = subtitle,
                Font = new Font("Segoe UI", 8.5F, FontStyle.Regular),
                ForeColor = Color.FromArgb(100, 116, 139),
                Location = new Point(34, 32),
                AutoSize = true
            };

            pnl.Controls.Add(lblTitle);
            pnl.Controls.Add(lblSub);
            return pnl;
        }
        #endregion

        #region Navigation Logic
        private void ShowPage(int pageIndex)
        {
            currentPage = pageIndex;

            pnlWelcome.Visible = (pageIndex == 0);
            pnlDestination.Visible = (pageIndex == 1);
            pnlTasks.Visible = (pageIndex == 2);
            pnlInstalling.Visible = (pageIndex == 3);
            pnlFinished.Visible = (pageIndex == 4);

            btnBack.Enabled = (pageIndex > 0 && pageIndex < 3);
            btnBack.Visible = (pageIndex < 4);

            btnCancel.Enabled = (pageIndex < 3);
            btnCancel.Visible = (pageIndex < 4);

            if (pageIndex == 2)
            {
                btnNext.Text = "&Install";
            }
            else if (pageIndex == 4)
            {
                btnNext.Text = "&Finish";
                btnNext.Enabled = true;
                btnNext.Focus();
            }
            else
            {
                btnNext.Text = "&Next >";
                btnNext.Enabled = (pageIndex != 3);
            }

            if (pageIndex == 3)
            {
                currentTipIndex = 0;
                lastReportedPercent = -1;
                if (tipTimer != null) tipTimer.Start();
                if (pnlIntelligence != null && !pnlIntelligence.IsDisposed) pnlIntelligence.Invalidate();
            }
            else
            {
                if (tipTimer != null) tipTimer.Stop();
            }
        }

        private void OnNextClicked()
        {
            if (currentPage == 0)
            {
                ShowPage(1);
            }
            else if (currentPage == 1)
            {
                string targetDir = txtInstallPath.Text.Trim();
                if (string.IsNullOrEmpty(targetDir))
                {
                    MessageBox.Show(this, "Please enter a destination folder path.", "Invalid Path", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                    return;
                }

                try
                {
                    string fullPath = Path.GetFullPath(targetDir);
                    if (Directory.Exists(fullPath) && Directory.GetFileSystemEntries(fullPath).Length > 0)
                    {
                        DialogResult res = MessageBox.Show(
                            this,
                            "The folder already exists and is not empty:\n" + fullPath + "\n\nWould you like to install to this folder anyway?",
                            "Folder Exists",
                            MessageBoxButtons.YesNo,
                            MessageBoxIcon.Question
                        );
                        if (res != DialogResult.Yes) return;
                    }
                }
                catch (Exception ex)
                {
                    MessageBox.Show(this, "The specified path is invalid:\n" + ex.Message, "Invalid Path", MessageBoxButtons.OK, MessageBoxIcon.Error);
                    return;
                }

                ShowPage(2);
            }
            else if (currentPage == 2)
            {
                // Start Installation
                ShowPage(3);
                StartInstallation();
            }
            else if (currentPage == 4)
            {
                // Finish
                if (chkLaunchApp.Checked)
                {
                    LaunchInstalledApp();
                }
                this.Close();
            }
        }

        private void OnBackClicked()
        {
            if (currentPage > 0 && currentPage < 3)
            {
                ShowPage(currentPage - 1);
            }
        }

        private void OnCancelClicked()
        {
            DialogResult res = MessageBox.Show(
                this,
                "Setup is not complete. If you exit now, " + AppName + " will not be installed.\n\nAre you sure you want to exit Setup?",
                "Exit Setup",
                MessageBoxButtons.YesNo,
                MessageBoxIcon.Question
            );
            if (res == DialogResult.Yes)
            {
                this.Close();
            }
        }

        private void SetupWizardForm_FormClosing(object sender, FormClosingEventArgs e)
        {
            if (currentPage == 3 && installWorker.IsBusy)
            {
                e.Cancel = true;
                MessageBox.Show(this, "Please wait until the installation process has finished.", "Installation in Progress", MessageBoxButtons.OK, MessageBoxIcon.Information);
            }
            else
            {
                if (tipTimer != null)
                {
                    tipTimer.Stop();
                    tipTimer.Dispose();
                    tipTimer = null;
                }
            }
        }
        #endregion

        #region Installation Worker & Engine
        private void StartInstallation()
        {
            string installPath = Path.GetFullPath(txtInstallPath.Text.Trim());
            bool createDesktop = chkDesktopShortcut.Checked;
            bool createStartMenu = chkStartMenuShortcut.Checked;
            bool autoStart = chkAutoStart.Checked;

            object[] args = new object[] { installPath, createDesktop, createStartMenu, autoStart };
            installWorker.RunWorkerAsync(args);
        }

        private void InstallWorker_DoWork(object sender, DoWorkEventArgs e)
        {
            object[] args = (object[])e.Argument;
            string installDir = (string)args[0];
            bool createDesktop = (bool)args[1];
            bool createStartMenu = (bool)args[2];
            bool autoStart = (bool)args[3];

            // 0. Terminate any previous running php.exe or cloudflared processes from old installation
            installWorker.ReportProgress(1, "Closing any running application instances...");
            try
            {
                foreach (string pName in new string[] { "php", "App", "cloudflared" })
                {
                    foreach (var proc in Process.GetProcessesByName(pName))
                    {
                        try { proc.Kill(); } catch { }
                    }
                }
            }
            catch { }

            // 1. Ensure target directory exists
            if (!Directory.Exists(installDir))
            {
                Directory.CreateDirectory(installDir);
            }

            // 2. Open embedded payload.zip stream
            Assembly asm = Assembly.GetExecutingAssembly();
            Stream zipStream = asm.GetManifestResourceStream("payload.zip");
            if (zipStream == null)
            {
                throw new InvalidOperationException("Embedded payload resource 'payload.zip' was not found in setup binary.");
            }

            using (zipStream)
            using (ZipArchive archive = new ZipArchive(zipStream, ZipArchiveMode.Read))
            {
                int totalEntries = archive.Entries.Count;
                int currentEntry = 0;

                foreach (ZipArchiveEntry entry in archive.Entries)
                {
                    currentEntry++;
                    int percent = (int)(((double)currentEntry / Math.Max(1, totalEntries)) * 90.0);

                    string fullDestPath = Path.GetFullPath(Path.Combine(installDir, entry.FullName));
                    if (!fullDestPath.StartsWith(installDir, StringComparison.OrdinalIgnoreCase))
                    {
                        continue; // Protect against Zip Slip
                    }

                    if (string.IsNullOrEmpty(entry.Name))
                    {
                        // Directory
                        Directory.CreateDirectory(fullDestPath);
                    }
                    else
                    {
                        // Ensure parent directory exists
                        string parentDir = Path.GetDirectoryName(fullDestPath);
                        if (!Directory.Exists(parentDir))
                        {
                            Directory.CreateDirectory(parentDir);
                        }

                        // Extract file
                        entry.ExtractToFile(fullDestPath, true);
                    }

                    string statusMsg = string.Format("Extracting: {0}", entry.FullName);
                    installWorker.ReportProgress(percent, statusMsg);
                }
            }

            // 3. Extract Uninstaller
            installWorker.ReportProgress(92, "Setting up uninstaller...");
            string uninstallerTarget = Path.Combine(installDir, "uninstall.exe");
            Stream uninstallerStream = asm.GetManifestResourceStream("uninstall.exe");
            if (uninstallerStream != null)
            {
                using (uninstallerStream)
                using (FileStream fs = new FileStream(uninstallerTarget, FileMode.Create, FileAccess.Write))
                {
                    uninstallerStream.CopyTo(fs);
                }
            }

            // 3b. Grant standard Users Modify permissions on the installation directory so all background runtime writes succeed
            installWorker.ReportProgress(94, "Configuring workspace permissions...");
            try
            {
                ProcessStartInfo icaclsPsi = new ProcessStartInfo
                {
                    FileName = "icacls.exe",
                    Arguments = string.Format("\"{0}\" /grant *S-1-5-32-545:(OI)(CI)M /T /Q", installDir),
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                using (Process p = Process.Start(icaclsPsi))
                {
                    p.WaitForExit(5000);
                }
            }
            catch { }

            // 4. Create Desktop Shortcut
            string appExePath = Path.Combine(installDir, "App.exe");
            string iconPath = Path.Combine(installDir, "app.ico");
            if (!File.Exists(iconPath) && appIcon != null)
            {
                using (FileStream fs = new FileStream(iconPath, FileMode.Create, FileAccess.Write))
                {
                    appIcon.Save(fs);
                }
            }

            if (createDesktop)
            {
                installWorker.ReportProgress(95, "Creating Desktop shortcut...");
                string targetDesktop = Environment.GetFolderPath(Environment.SpecialFolder.CommonDesktopDirectory);
                if (string.IsNullOrEmpty(targetDesktop) || !Directory.Exists(targetDesktop))
                {
                    targetDesktop = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                }
                if (!string.IsNullOrEmpty(targetDesktop))
                {
                    CreateShortcut(Path.Combine(targetDesktop, AppName + ".lnk"), appExePath, installDir, iconPath, "Launch " + AppName);
                }
            }

            // 5. Create Start Menu Shortcuts
            if (createStartMenu)
            {
                installWorker.ReportProgress(96, "Creating Start Menu shortcuts...");
                string[] startMenuRoots = new string[]
                {
                    Environment.GetFolderPath(Environment.SpecialFolder.StartMenu),
                    Environment.GetFolderPath(Environment.SpecialFolder.CommonStartMenu)
                };

                foreach (string root in startMenuRoots)
                {
                    if (string.IsNullOrEmpty(root)) continue;
                    try
                    {
                        string startMenuDir = Path.Combine(root, "Programs", AppName);
                        if (!Directory.Exists(startMenuDir))
                        {
                            Directory.CreateDirectory(startMenuDir);
                        }

                        string startMenuLnk = Path.Combine(startMenuDir, AppName + ".lnk");
                        CreateShortcut(startMenuLnk, appExePath, installDir, iconPath, "Launch " + AppName);

                        if (File.Exists(uninstallerTarget))
                        {
                            string uninstLnk = Path.Combine(startMenuDir, "Uninstall " + AppName + ".lnk");
                            CreateShortcut(uninstLnk, uninstallerTarget, installDir, iconPath, "Uninstall " + AppName);
                        }
                    }
                    catch { }
                }
            }

            // 6. Windows Registry Registration (Apps & Features / Control Panel)
            installWorker.ReportProgress(98, "Registering application with Windows...");
            RegistryKey[] rootKeys = new RegistryKey[] { Registry.LocalMachine, Registry.CurrentUser };
            foreach (RegistryKey root in rootKeys)
            {
                try
                {
                    using (RegistryKey key = root.CreateSubKey(
                        @"Software\Microsoft\Windows\CurrentVersion\Uninstall\" + AppRegKey))
                    {
                        if (key != null)
                        {
                            key.SetValue("DisplayName", AppName);
                            key.SetValue("DisplayVersion", AppVersion);
                            key.SetValue("Publisher", AppPublisher);
                            key.SetValue("DisplayIcon", iconPath);
                            key.SetValue("InstallLocation", installDir);
                            key.SetValue("UninstallString", "\"" + uninstallerTarget + "\"");
                            key.SetValue("QuietUninstallString", "\"" + uninstallerTarget + "\" --silent");
                            key.SetValue("ModifyPath", "\"" + uninstallerTarget + "\"");
                            key.SetValue("NoModify", 1, RegistryValueKind.DWord);
                            key.SetValue("NoRepair", 1, RegistryValueKind.DWord);
                            key.SetValue("EstimatedSize", 125000, RegistryValueKind.DWord); // ~125MB in KB
                            key.SetValue("URLInfoAbout", "https://tunneldeck.online");
                        }
                    }
                }
                catch { }
            }

            // 7. Auto-start on boot if requested
            if (autoStart)
            {
                try
                {
                    using (RegistryKey runKey = Registry.CurrentUser.OpenSubKey(
                        @"Software\Microsoft\Windows\CurrentVersion\Run", true))
                    {
                        if (runKey != null)
                        {
                            runKey.SetValue(AppRegKey, "\"" + appExePath + "\"");
                        }
                    }
                }
                catch { }
            }

            installWorker.ReportProgress(100, "Installation complete!");
            Thread.Sleep(300);
        }

        private void InstallWorker_ProgressChanged(object sender, ProgressChangedEventArgs e)
        {
            progressBar.Value = Math.Min(100, Math.Max(0, e.ProgressPercentage));
            if (e.UserState != null)
            {
                string msg = e.UserState.ToString();
                if (msg.Length > 65)
                {
                    msg = "..." + msg.Substring(msg.Length - 62);
                }
                lblCurrentFile.Text = msg;
            }

            // Dynamically cycle intelligence tips across installation progress milestones
            if (e.ProgressPercentage >= 75 && lastReportedPercent < 75)
            {
                currentTipIndex = 3;
                if (pnlIntelligence != null && !pnlIntelligence.IsDisposed) pnlIntelligence.Invalidate();
            }
            else if (e.ProgressPercentage >= 50 && lastReportedPercent < 50)
            {
                currentTipIndex = 2;
                if (pnlIntelligence != null && !pnlIntelligence.IsDisposed) pnlIntelligence.Invalidate();
            }
            else if (e.ProgressPercentage >= 25 && lastReportedPercent < 25)
            {
                currentTipIndex = 1;
                if (pnlIntelligence != null && !pnlIntelligence.IsDisposed) pnlIntelligence.Invalidate();
            }
            lastReportedPercent = e.ProgressPercentage;
        }

        private void InstallWorker_RunWorkerCompleted(object sender, RunWorkerCompletedEventArgs e)
        {
            if (tipTimer != null) tipTimer.Stop();

            if (e.Error != null)
            {
                MessageBox.Show(
                    this,
                    "An error occurred during installation:\n" + e.Error.Message,
                    "Installation Failed",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
                ShowPage(1);
            }
            else
            {
                ShowPage(4);
            }
        }

        private void CreateShortcut(string shortcutPath, string targetPath, string workingDir, string iconLocation, string description)
        {
            try
            {
                Type shellType = Type.GetTypeFromProgID("WScript.Shell");
                if (shellType != null)
                {
                    dynamic shell = Activator.CreateInstance(shellType);
                    dynamic shortcut = shell.CreateShortcut(shortcutPath);
                    shortcut.TargetPath = targetPath;
                    shortcut.WorkingDirectory = workingDir;
                    shortcut.Description = description;
                    if (!string.IsNullOrEmpty(iconLocation) && File.Exists(iconLocation))
                    {
                        shortcut.IconLocation = iconLocation + ",0";
                    }
                    shortcut.Save();
                }
            }
            catch { }
        }

        private void LaunchInstalledApp()
        {
            try
            {
                string installDir = txtInstallPath.Text.Trim();
                string appExe = Path.Combine(installDir, "App.exe");
                if (File.Exists(appExe))
                {
                    try
                    {
                        // Launch via explorer.exe so the app runs with the logged-in user's standard token, NOT elevated Administrator
                        Process.Start("explorer.exe", "\"" + appExe + "\"");
                        return;
                    }
                    catch { }

                    ProcessStartInfo psi = new ProcessStartInfo
                    {
                        FileName = appExe,
                        WorkingDirectory = installDir,
                        UseShellExecute = true
                    };
                    Process.Start(psi);
                }
                else
                {
                    string serveBat = Path.Combine(installDir, "serve.bat");
                    if (File.Exists(serveBat))
                    {
                        try
                        {
                            Process.Start("explorer.exe", "\"" + serveBat + "\"");
                            return;
                        }
                        catch { }

                        ProcessStartInfo psi = new ProcessStartInfo
                        {
                            FileName = serveBat,
                            WorkingDirectory = installDir,
                            UseShellExecute = true
                        };
                        Process.Start(psi);
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show(this, "Could not launch " + AppName + ":\n" + ex.Message, "Launch Error", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }
        #endregion

        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

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
                        "Administrator privileges are required to install " + AppName + " to Program Files.",
                        "Elevation Required",
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Warning
                    );
                    return;
                }
            }

            Application.Run(new SetupWizardForm());
        }

        private static bool IsAdministrator()
        {
            using (System.Security.Principal.WindowsIdentity identity = System.Security.Principal.WindowsIdentity.GetCurrent())
            {
                System.Security.Principal.WindowsPrincipal principal = new System.Security.Principal.WindowsPrincipal(identity);
                return principal.IsInRole(System.Security.Principal.WindowsBuiltInRole.Administrator);
            }
        }
    }
}

