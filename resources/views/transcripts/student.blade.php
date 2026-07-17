<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Academic Transcript — {{ $name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; margin: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 3px solid #2563eb; padding-bottom: 16px; margin-bottom: 24px; }
        .school { font-size: 1.4rem; font-weight: 800; color: #1d4ed8; }
        .title { font-size: 1.05rem; color: #64748b; }
        .meta { text-align: right; font-size: 0.85rem; color: #334155; line-height: 1.5; }
        .student { margin-bottom: 24px; font-size: 0.9rem; }
        .student strong { color: #0f172a; }
        h2 { font-size: 1rem; color: #1d4ed8; margin: 24px 0 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #f1f5f9; }
        td.num, th.num { text-align: center; }
        .avg { font-weight: 700; color: #2563eb; }
        .footer { margin-top: 32px; font-size: 0.75rem; color: #94a3b8; text-align: center; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="school">Passerelles Numériques Cambodia</div>
            <div class="title">Official Academic Transcript</div>
        </div>
        <div class="meta">
            <div><strong>{{ $name }}</strong></div>
            <div>Student ID: {{ $studentId }}</div>
            <div>{{ $generation }}</div>
        </div>
    </div>

    @foreach ($terms as $term)
        <h2>{{ $term['term'] }} — Average: <span class="avg">{{ $term['average'] }}</span></h2>
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
                    <tr>
                        <td>{{ $s['subject'] }}</td>
                        <td class="num">{{ $s['quiz'] !== null ? $s['quiz'] : '—' }}</td>
                        <td class="num">{{ $s['assignment'] !== null ? $s['assignment'] : '—' }}</td>
                        <td class="num">{{ $s['midterm'] !== null ? $s['midterm'] : '—' }}</td>
                        <td class="num">{{ $s['final'] !== null ? $s['final'] : '—' }}</td>
                        <td class="num">{{ $s['total'] !== null ? $s['total'] : '—' }}</td>
                        <td class="num">{{ $s['grade'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="footer">Generated on {{ date('Y-m-d H:i') }} — This document is system-generated.</div>
</body>
</html>
