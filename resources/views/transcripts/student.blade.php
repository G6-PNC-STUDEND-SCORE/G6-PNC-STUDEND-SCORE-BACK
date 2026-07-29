<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Academic Transcript — {{ $name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #0f172a;
            background: #f8fafc;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Page wrapper ── */
        .page {
            max-width: 1000px;
            margin: 0 auto;
            background: #ffffff;
            min-height: 100vh;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.06);
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #6366f1 100%);
            padding: 32px 40px 28px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
        }
        .header-left { flex: 1; }
        .school-name {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .school-sub {
            font-size: 0.85rem;
            font-weight: 400;
            opacity: 0.8;
            margin-top: 4px;
        }
        .header-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-top: 10px;
        }
        .header-right {
            text-align: right;
            flex-shrink: 0;
        }
        .student-name {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .student-meta {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-top: 3px;
            line-height: 1.5;
        }

        /* ── Body ── */
        .body-inner {
            padding: 32px 40px 40px;
        }

        /* ── Term Section ── */
        .term-section {
            margin-bottom: 32px;
        }
        .term-section:last-child {
            margin-bottom: 0;
        }

        .term-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eef2ff;
        }
        .term-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e3a5f;
            letter-spacing: -0.01em;
        }
        .term-average {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
        }
        .term-average .avg-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            padding: 2px 10px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .avg-high { background: #f0fdf4; color: #16a34a; }
        .avg-mid { background: #eff6ff; color: #2563eb; }
        .avg-low { background: #fef2f2; color: #dc2626; }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }
        thead th {
            background: #f8fafc;
            padding: 10px 14px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            text-align: left;
            border-bottom: 1.5px solid #e2e8f0;
        }
        thead th.num {
            text-align: center;
        }
        tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #334155;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        tbody tr:hover {
            background: #f1f5f9;
        }
        td.num {
            text-align: center;
            font-variant-numeric: tabular-nums;
            font-weight: 500;
        }
        td.subject-cell {
            font-weight: 600;
            color: #0f172a;
        }

        /* Grade badge */
        .grade-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            padding: 1px 10px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .grade-a { background: #f0fdf4; color: #16a34a; }
        .grade-b { background: #eff6ff; color: #2563eb; }
        .grade-c { background: #fffbeb; color: #d97706; }
        .grade-d { background: #fff7ed; color: #ea580c; }
        .grade-f { background: #fef2f2; color: #dc2626; }
        .grade-none { background: #f1f5f9; color: #94a3b8; }

        /* Score pill */
        .score-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            padding: 1px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .score-high { background: #f0fdf4; color: #16a34a; }
        .score-good { background: #eff6ff; color: #2563eb; }
        .score-mid { background: #fffbeb; color: #d97706; }
        .score-low { background: #fef2f2; color: #dc2626; }
        .score-na { color: #94a3b8; }

        /* ── Footer ── */
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.72rem;
            color: #94a3b8;
        }
        .footer-seal {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .footer-seal svg { opacity: 0.5; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state p {
            margin-top: 8px;
            font-size: 0.9rem;
        }

        /* ── Print ── */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .page {
                max-width: 100%;
                box-shadow: none;
                min-height: auto;
            }
            .header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .header-badge {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tbody tr:nth-child(even) {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .grade-badge, .score-pill, .avg-value {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tbody tr:hover {
                background: inherit;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="school-name">Passerelles Numériques Cambodia</div>
                <div class="school-sub">Official Academic Transcript</div>
                <div class="header-badge">&#9733; Verified Document</div>
            </div>
            <div class="header-right">
                <div class="student-name">{{ $name }}</div>
                <div class="student-meta">
                    @if ($studentId)<div>ID: {{ $studentId }}</div>@endif
                    @if ($generation)<div>{{ $generation }}</div>@endif
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="body-inner">
            @forelse ($terms as $term)
                @php
                    $avg = $term['average'];
                    $avgClass = $avg >= 85 ? 'avg-high' : ($avg >= 60 ? 'avg-mid' : 'avg-low');
                @endphp
                <div class="term-section">
                    <div class="term-header">
                        <div class="term-title">{{ $term['term'] }}</div>
                        <div class="term-average">
                            Average
                            <span class="avg-value {{ $avgClass }}">{{ $avg }}</span>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th class="num">Quiz</th>
                                <th class="num">Assignment</th>
                                <th class="num">Midterm</th>
                                <th class="num">Final</th>
                                <th class="num">Total</th>
                                <th class="num">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($term['subjects'] as $s)
                                @php
                                    $total = $s['total'];
                                    $totalClass = $total !== null ? ($total >= 90 ? 'score-high' : ($total >= 75 ? 'score-good' : ($total >= 60 ? 'score-mid' : 'score-low'))) : 'score-na';
                                    $grade = $s['grade'] ?? null;
                                    $gradeClass = $grade ? (in_array(strtoupper($grade), ['A+','A','A-']) ? 'grade-a' : (in_array(strtoupper($grade), ['B+','B','B-']) ? 'grade-b' : (in_array(strtoupper($grade), ['C+','C','C-']) ? 'grade-c' : (in_array(strtoupper($grade), ['D+','D']) ? 'grade-d' : 'grade-f')))) : 'grade-none';
                                @endphp
                                <tr>
                                    <td class="subject-cell">{{ $s['subject'] }}</td>
                                    <td class="num">{{ $s['quiz'] !== null ? $s['quiz'] : '—' }}</td>
                                    <td class="num">{{ $s['assignment'] !== null ? $s['assignment'] : '—' }}</td>
                                    <td class="num">{{ $s['midterm'] !== null ? $s['midterm'] : '—' }}</td>
                                    <td class="num">{{ $s['final'] !== null ? $s['final'] : '—' }}</td>
                                    <td class="num">
                                        @if ($total !== null)
                                            <span class="score-pill {{ $totalClass }}">{{ $total }}</span>
                                        @else
                                            <span class="score-na">—</span>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if ($grade)
                                            <span class="grade-badge {{ $gradeClass }}">{{ $grade }}</span>
                                        @else
                                            <span class="grade-badge grade-none">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                    <p>No academic records found for this student.</p>
                </div>
            @endforelse

            <!-- Footer -->
            <div class="footer">
                <span>Generated on {{ date('Y-m-d H:i') }}</span>
                <span class="footer-seal">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    System-generated document
                </span>
            </div>
        </div>
    </div>
</body>
</html>
