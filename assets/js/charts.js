// Universe System — Chart.js Initializations
document.addEventListener('DOMContentLoaded', () => {
  const colors = {
    cyan: '#22d3ee', green: '#10b981', amber: '#f59e0b', red: '#ef4444',
    indigo: '#6366f1', blue: '#3b82f6', purple: '#a855f7',
    grid: '#1e293b', text: '#64748b', bg: '#111827'
  };

  Chart.defaults.color = colors.text;
  Chart.defaults.borderColor = colors.grid;
  Chart.defaults.font.family = "'JetBrains Mono', monospace";
  Chart.defaults.font.size = 10;
  Chart.defaults.plugins.legend.display = false;
  Chart.defaults.scale = Chart.defaults.scale || {};

  // Dashboard Charts
  if (window.chartData && window.chartData.dashboard) {
    const dashTrendEl = document.getElementById('dashTrendChart');
    if (dashTrendEl) {
      new Chart(dashTrendEl, {
        type: 'line',
        data: {
          labels: window.chartData.dashboard.trendLabels,
          datasets: [{
            label: 'Avg Entropy',
            data: window.chartData.dashboard.trendData,
            borderColor: colors.cyan,
            backgroundColor: 'rgba(34,211,238,0.1)',
            fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: colors.cyan
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            y: { grid: { color: colors.grid } },
            x: { grid: { display: false } }
          }
        }
      });
    }

    const dashDistEl = document.getElementById('dashDistChart');
    if (dashDistEl) {
      new Chart(dashDistEl, {
        type: 'doughnut',
        data: {
          labels: ['Discard', 'Compress', 'Preserve', 'Archive'],
          datasets: [{
            data: window.chartData.dashboard.decisionDist,
            backgroundColor: [colors.red, colors.blue, colors.green, colors.amber],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: true, position: 'right' } }
        }
      });
    }
  }

  // State Changes: Change Type Distribution Chart
  const driftEl = document.getElementById('driftChart');
  if (driftEl && window.chartData && window.chartData.changes) {
    new Chart(driftEl, {
      type: 'pie',
      data: {
        labels: ['CREATE', 'UPDATE', 'DELETE', 'CORRUPTION'],
        datasets: [{
          data: window.chartData.changes.types,
          backgroundColor: [colors.cyan, colors.green, colors.indigo, colors.red],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'right' } }
      }
    });
  }

  // Decisions: Decision count chart
  const threshEl = document.getElementById('entropyThresholdChart');
  if (threshEl && window.chartData && window.chartData.decisions) {
    new Chart(threshEl, {
      type: 'bar',
      data: {
        labels: ['Discard', 'Compress', 'Preserve', 'Archive'],
        datasets: [{
          data: window.chartData.decisions.counts,
          backgroundColor: [colors.red, colors.blue, colors.green, colors.amber],
          borderRadius: 3, barPercentage: 0.6
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: {
          y: { display: false },
          x: { grid: { display: false }}
        }
      }
    });
  }

  // Entropy Velocity mini bars (State Changes page)
  const velEl = document.getElementById('velocityChart');
  if (velEl) {
    new Chart(velEl, {
      type: 'bar',
      data: {
        labels: ['', '', '', '', '', '', ''],
        datasets: [{
          data: [12, 14, 13, 15, 14, 18, 11],
          backgroundColor: [colors.indigo, colors.indigo, colors.indigo, colors.indigo, colors.indigo, colors.amber, colors.indigo],
          borderRadius: 2, barPercentage: 0.7
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { display: false }, x: { display: false }},
        plugins: { tooltip: { enabled: false }}
      }
    });
  }

  // Sector Decay charts (Entropy page)
  if (window.chartData && window.chartData.entropy) {
    const sectors = window.chartData.entropy.sectors || [];
    document.querySelectorAll('.sector-chart').forEach((el, i) => {
      const dataPoints = sectors[i] ? sectors[i].scores : [];
      // Pad to 10 points
      while (dataPoints.length < 10) dataPoints.unshift(0);
      new Chart(el, {
        type: 'bar',
        data: {
          labels: ['','','','','','','','','',''],
          datasets: [{
            data: dataPoints,
            backgroundColor: colors.cyan, borderRadius: 1, barPercentage: 0.7
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: { y: { display: false, min: 0, max: 1 }, x: { display: false }},
          plugins: { tooltip: { enabled: false } }
        }
      });
    });
  }

  // Mean Entropy Gauge (Entropy page) - drawn with canvas
  const gaugeEl = document.getElementById('entropyGauge');
  if (gaugeEl) {
    const ctx = gaugeEl.getContext('2d');
    const w = gaugeEl.width, h = gaugeEl.height;
    const cx = w/2, cy = h/2 + 20;
    const r = Math.min(w,h)/2 - 20;
    const startAngle = Math.PI * 0.8;
    const endAngle = Math.PI * 2.2;
    const avg = window.chartData && window.chartData.entropy ? window.chartData.entropy.avgEntropy : 0;
    const value = Math.max(0, Math.min(1, avg)); // Cap 0 to 1
    const valueAngle = startAngle + (endAngle - startAngle) * value;

    // Background arc
    ctx.beginPath();
    ctx.arc(cx, cy, r, startAngle, endAngle);
    ctx.strokeStyle = colors.grid;
    ctx.lineWidth = 8;
    ctx.lineCap = 'round';
    ctx.stroke();

    // Value arc
    const grad = ctx.createLinearGradient(0, 0, w, 0);
    grad.addColorStop(0, colors.indigo);
    grad.addColorStop(1, colors.cyan);
    ctx.beginPath();
    ctx.arc(cx, cy, r, startAngle, valueAngle);
    ctx.strokeStyle = grad;
    ctx.lineWidth = 10;
    ctx.lineCap = 'round';
    ctx.stroke();
  }

  // Animated counters
  document.querySelectorAll('[data-count]').forEach(el => {
    const target = parseFloat(el.dataset.count);
    const decimals = (el.dataset.decimals || 0) | 0;
    const duration = 1200;
    const start = performance.now();
    const tick = now => {
      const p = Math.min((now - start) / duration, 1);
      const ease = 1 - Math.pow(1 - p, 3);
      el.textContent = (target * ease).toFixed(decimals);
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });
});
