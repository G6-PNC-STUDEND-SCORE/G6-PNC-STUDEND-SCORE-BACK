<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Distribution Chart - Student Score System</title>
    @vite(['resources/css/app.css', 'resources/js/grade-chart.js'])
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }

        .header p {
            color: #94a3b8;
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            border-color: rgba(96, 165, 250, 0.3);
            box-shadow: 0 8px 30px rgba(96, 165, 250, 0.15);
        }

        .summary-card .icon {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .summary-card .label {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        .summary-card:nth-child(1) .value { color: #60a5fa; }
        .summary-card:nth-child(2) .value { color: #a78bfa; }
        .summary-card:nth-child(3) .value { color: #f472b6; }
        .summary-card:nth-child(4) .value { color: #34d399; }

        /* Chart Section */
        .chart-section {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1.5rem;
            padding: 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .chart-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #e2e8f0;
            margin-bottom: 0.5rem;
        }

        .chart-section .subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            min-height: 550px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-wrapper canvas {
            width: 100% !important;
            max-height: 600px;
        }

        /* Legend / Grade Key */
        .grade-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .grade-legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #cbd5e1;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .grade-legend-item .dot {
            width: 14px;
            height: 14px;
            border-radius: 4px;
        }

        /* Subject performance chart area */
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Loading state */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem;
            color: #64748b;
        }

        .loading .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(96, 165, 250, 0.2);
            border-top-color: #60a5fa;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .header h1 { font-size: 1.8rem; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .chart-row { grid-template-columns: 1fr; }
            .chart-wrapper { min-height: 350px; }
            .grade-legend { gap: 1rem; }
        }

        /* Footer */
        .footer {
            text-align: center;
            color: #475569;
            font-size: 0.85rem;
            padding: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Grade Distribution Dashboard</h1>
            <p>Student Score Management System — Performance Overview</p>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid" id="summaryGrid">
            <div class="summary-card">
                <div class="icon">🎓</div>
                <div class="value" id="totalStudents">—</div>
                <div class="label">Total Students</div>
            </div>
            <div class="summary-card">
                <div class="icon">📝</div>
                <div class="value" id="totalScores">—</div>
                <div class="label">Total Scores</div>
            </div>
            <div class="summary-card">
                <div class="icon">📚</div>
                <div class="value" id="totalSubjects">—</div>
                <div class="label">Subjects</div>
            </div>
            <div class="summary-card">
                <div class="icon">🏆</div>
                <div class="value" id="avgScore">—</div>
                <div class="label">Average Score</div>
            </div>
        </div>

        <!-- Main Chart: Grade Distribution -->
        <div class="chart-section">
            <h2>📈 Grade Distribution</h2>
            <p class="subtitle">Breakdown of student grades across all subjects and classes</p>
            <div id="gradeChartLoading" class="loading">
                <div class="spinner"></div>
                <span>Loading chart data...</span>
            </div>
            <div class="chart-wrapper" id="gradeChartContainer" style="display:none;">
                <canvas id="gradeChart"></canvas>
            </div>
            <div class="grade-legend" id="gradeLegend"></div>
        </div>

        <!-- Secondary Charts Row -->
        <div class="chart-row">
            <div class="chart-section">
                <h2>📊 Subject Performance</h2>
                <p class="subtitle">Average scores per subject</p>
                <div id="subjectChartLoading" class="loading">
                    <div class="spinner"></div>
                    <span>Loading...</span>
                </div>
                <div class="chart-wrapper" id="subjectChartContainer" style="display:none; min-height: 350px;">
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>

            <div class="chart-section">
                <h2>🥧 Grade Distribution (Pie)</h2>
                <p class="subtitle">Proportion of each grade category</p>
                <div id="pieChartLoading" class="loading">
                    <div class="spinner"></div>
                    <span>Loading...</span>
                </div>
                <div class="chart-wrapper" id="pieChartContainer" style="display:none; min-height: 350px;">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>

        <div class="footer">
            Student Score Management System &copy; {{ date('Y') }}
        </div>
    </div>
</body>
</html>
