import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;

Alpine.start();

/**
 * EduSystem.md 1B -- the student's progress-over-time line chart.
 *
 * The dashboard publishes its series as window.LEARNSYNC_PROGRESS, keyed by
 * course title, each holding the snapshot labels and percentages. One line per
 * course, so a student can see every course trending at once.
 */
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('progressChart');

    if (!canvas || !window.LEARNSYNC_PROGRESS) {
        return;
    }

    const series = window.LEARNSYNC_PROGRESS;
    const palette = ['#1d4ed8', '#059669', '#b45309', '#7c3aed', '#be123c'];

    // Snapshots are taken per course, so the courses do not share an x-axis.
    // The longest run of labels becomes the axis and shorter lines simply stop.
    const labels = Object.values(series)
        .reduce((longest, s) => (s.labels.length > longest.length ? s.labels : longest), []);

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: Object.entries(series).map(([title, s], index) => ({
                label: title,
                data: s.points,
                borderColor: palette[index % palette.length],
                backgroundColor: palette[index % palette.length] + '20',
                tension: 0.3,
                fill: true,
                pointRadius: 3,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: (value) => `${value}%` },
                },
            },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}%` } },
            },
        },
    });
});
