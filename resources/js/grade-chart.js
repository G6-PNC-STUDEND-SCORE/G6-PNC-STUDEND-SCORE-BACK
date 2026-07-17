import './bootstrap';
import Chart from 'chart.js/auto';

const API_BASE = '/api';

// Color palette for grades
const GRADE_COLORS = {
    'A': { bg: 'rgba(34, 197, 94, 0.85)', border: '#22c55e' },
    'B': { bg: 'rgba(59, 130, 246, 0.85)', border: '#3b82f6' },
    'C': { bg: 'rgba(245, 158, 11, 0.85)', border: '#f59e0b' },
    'D': { bg: 'rgba(249, 115, 22, 0.85)', border: '#f97316' },
    'F': { bg: 'rgba(239, 68, 68, 0.85)', border: '#ef4444' },
};

const GRADE_ORDER = ['A', 'B', 'C', 'D', 'F'];

// Fetch data with error handling
async function fetchJson(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    return res.json();
}

// Update summary cards
function updateSummary(data) {
    document.getElementById('totalStudents').textContent = data.total_students;
    document.getElementById('totalScores').textContent = data.total_scores;
    document.getElementById('totalSubjects').textContent = data.total_subjects;
    document.getElementById('avgScore').textContent = data.average_score;
}

// Render Grade Distribution Bar Chart
function renderGradeChart(grades) {
    const labels = grades.map(g => g.label);
    const counts = grades.map(g => g.count);
    const colors = grades.map(g => GRADE_COLORS[g.grade]?.bg || 'rgba(100, 116, 139, 0.8)');
    const borders = grades.map(g => GRADE_COLORS[g.grade]?.border || '#64748b');

    // Hide loading, show chart
    document.getElementById('gradeChartLoading').style.display = 'none';
    document.getElementById('gradeChartContainer').style.display = 'block';

    const ctx = document.getElementById('gradeChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Students',
                data: counts,
                backgroundColor: colors,
                borderColor: borders,
                borderWidth: 2,
                borderRadius: 8,
                barPercentage: 0.65,
                categoryPercentage: 0.8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        afterLabel: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((context.parsed.y / total) * 100).toFixed(1);
                            return `Percentage: ${pct}%`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 13, weight: '500' },
                        stepSize: 1,
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.08)',
                    },
                    title: {
                        display: true,
                        text: 'Number of Students',
                        color: '#64748b',
                        font: { size: 14, weight: '600' },
                    }
                },
                x: {
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 14, weight: '600' },
                    },
                    grid: {
                        display: false,
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart',
            },
        }
    });

    // Build legend
    const legendEl = document.getElementById('gradeLegend');
    legendEl.innerHTML = grades.map(g => `
        <div class="grade-legend-item">
            <span class="dot" style="background: ${GRADE_COLORS[g.grade]?.bg || '#64748b'};"></span>
            ${g.grade}: ${g.count} students
        </div>
    `).join('');
}

// Render Subject Performance Horizontal Bar Chart
function renderSubjectChart(subjects) {
    document.getElementById('subjectChartLoading').style.display = 'none';
    document.getElementById('subjectChartContainer').style.display = 'block';

    const ctx = document.getElementById('subjectChart').getContext('2d');
    const labels = subjects.map(s => s.subject);
    const scores = subjects.map(s => parseFloat(s.average_score));
    const studentCounts = subjects.map(s => s.student_count);

    // Generate gradient colors
    const colors = scores.map((score, i) => {
        const intensity = 0.5 + (i / scores.length) * 0.5;
        return {
            bg: `rgba(96, 165, 250, ${intensity})`,
            border: `rgba(96, 165, 250, 1)`,
        };
    });

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Score',
                data: scores,
                backgroundColor: colors.map(c => c.bg),
                borderColor: colors.map(c => c.border),
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        afterLabel: function(context) {
                            return `Students: ${studentCounts[context.dataIndex]}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 12 },
                        callback: function(value) { return value + '%'; }
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.08)',
                    },
                    title: {
                        display: true,
                        text: 'Average Score (%)',
                        color: '#64748b',
                        font: { size: 13, weight: '600' },
                    }
                },
                y: {
                    ticks: {
                        color: '#94a3b8',
                        font: { size: 12, weight: '600' },
                    },
                    grid: {
                        display: false,
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart',
            },
        }
    });
}

// Render Pie Chart
function renderPieChart(grades) {
    document.getElementById('pieChartLoading').style.display = 'none';
    document.getElementById('pieChartContainer').style.display = 'block';

    const ctx = document.getElementById('pieChart').getContext('2d');
    const labels = grades.map(g => g.grade);
    const data = grades.map(g => g.count);
    const colors = grades.map(g => GRADE_COLORS[g.grade]?.bg || 'rgba(100, 116, 139, 0.8)');
    const borders = grades.map(g => GRADE_COLORS[g.grade]?.border || '#64748b');

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderColor: borders,
                borderWidth: 3,
                hoverOffset: 15,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#94a3b8',
                        font: { size: 14, weight: '600' },
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle',
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleColor: '#e2e8f0',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(148, 163, 184, 0.2)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed} students (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '50%',
            animation: {
                animateRotate: true,
                duration: 1200,
                easing: 'easeOutQuart',
            },
        }
    });
}

// Initialize everything
async function init() {
    try {
        // Fetch all data in parallel
        const [summaryData, gradeData, subjectData] = await Promise.all([
            fetchJson(`${API_BASE}/chart/summary`),
            fetchJson(`${API_BASE}/chart/grade-distribution`),
            fetchJson(`${API_BASE}/chart/subject-performance`),
        ]);

        if (summaryData.success) updateSummary(summaryData.data);
        if (gradeData.success) {
            renderGradeChart(gradeData.data.grades);
            renderPieChart(gradeData.data.grades);
        }
        if (subjectData.success) renderSubjectChart(subjectData.data);

    } catch (error) {
        console.error('Failed to load chart data:', error);
        document.querySelectorAll('.loading').forEach(el => {
            el.innerHTML = `<span style="color: #ef4444;">❌ Failed to load chart data. Please try again later.</span>`;
        });
    }
}

// Start when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
