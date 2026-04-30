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

  // Drift Timeline (State Changes page)
  const driftEl = document.getElementById('driftChart');
  if (driftEl) {
    new Chart(driftEl, {
      type: 'line',
      data: {
        labels: ['ALPHA-1', 'GAMMA-4', 'DELTA-PRIME', 'OMEGA-SEC', 'CURRENT'],
        datasets: [{
          label: 'Composition',
          data: [0.6, 0.5, 0.45, 0.35, 0.3],
          borderColor: colors.cyan,
          backgroundColor: 'rgba(34,211,238,0.08)',
          fill: true, tension: 0.4, pointRadius: 4,
          pointBackgroundColor: colors.cyan
        }, {
          label: 'Density',
          data: [0.3, 0.28, 0.25, 0.22, 0.2],
          borderColor: colors.amber,
          backgroundColor: 'rgba(245,158,11,0.05)',
          fill: true, tension: 0.4, pointRadius: 3,
          pointBackgroundColor: colors.amber
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top', align: 'end',
          labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 }
        }},
        scales: {
          y: { grid: { color: colors.grid }, ticks: { callback: v => v.toFixed(1) + ' Pb' }},
          x: { grid: { display: false }}
        }
      }
    });
  }

  // Entropy Threshold (Decisions page)
  const threshEl = document.getElementById('entropyThresholdChart');
  if (threshEl) {
    new Chart(threshEl, {
      type: 'bar',
      data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          data: [35, 42, 38, 50, 45, 65, 58],
          backgroundColor: [colors.cyan, colors.cyan, colors.cyan, colors.cyan, colors.cyan, colors.red, colors.amber],
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
  document.querySelectorAll('.sector-chart').forEach((el, i) => {
    const datasets = [
      [8, 6, 5, 7, 4, 6, 5, 8, 6, 9],
      [3, 2, 0, 4, 0, 6, 0, 0, 0, 0],
      [7, 8, 6, 9, 7, 8, 9, 7, 8, 6]
    ];
    const driftData = [
      [1, 1, 0, 1, 0, 0, 1, 0, 1, 0],
      [0, 0, 0, 0, 0, 1, 0, 0, 0, 0],
      [1, 2, 1, 0, 1, 2, 1, 0, 2, 3]
    ];
    new Chart(el, {
      type: 'bar',
      data: {
        labels: ['','','','','','','','','',''],
        datasets: [{
          data: datasets[i] || datasets[0],
          backgroundColor: colors.cyan, borderRadius: 1, barPercentage: 0.7
        },{
          data: driftData[i] || driftData[0],
          backgroundColor: colors.amber, borderRadius: 1, barPercentage: 0.7
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { display: false }, x: { display: false }},
        plugins: { tooltip: { enabled: false }}
      }
    });
  });

  // Mean Entropy Gauge (Entropy page) - drawn with canvas
  const gaugeEl = document.getElementById('entropyGauge');
  if (gaugeEl) {
    const ctx = gaugeEl.getContext('2d');
    const w = gaugeEl.width, h = gaugeEl.height;
    const cx = w/2, cy = h/2 + 20;
    const r = Math.min(w,h)/2 - 20;
    const startAngle = Math.PI * 0.8;
    const endAngle = Math.PI * 2.2;
    const value = 0.642;
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
