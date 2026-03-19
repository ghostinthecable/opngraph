<?php
$config = require __DIR__ . '/config.php';
$pollInterval = $config['poll_interval'] ?? 5000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPNgraph - Firewall Traffic Visualizer</title>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #050a12;
            --surface: #0c1220;
            --surface2: #111d30;
            --border: #1a2744;
            --text: #e2e8f0;
            --text-dim: #64748b;
            --text-muted: #475569;
            --green: #10b981;
            --green-dim: rgba(16,185,129,0.15);
            --orange: #f59e0b;
            --orange-dim: rgba(245,158,11,0.15);
            --red: #ef4444;
            --red-dim: rgba(239,68,68,0.15);
            --blue: #3b82f6;
            --blue-dim: rgba(59,130,246,0.15);
            --cyan: #06b6d4;
            --purple: #a855f7;
            --purple-dim: rgba(168,85,247,0.15);
            --pink: #ec4899;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        /* ─── Header ─── */
        #header {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            background: rgba(5, 10, 18, 0.85);
            backdrop-filter: blur(20px) saturate(1.8);
            border-bottom: 1px solid var(--border);
            padding: 0 20px;
            height: 52px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .logo-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--green), var(--cyan));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 900;
            color: #000;
        }

        .logo span { color: var(--green); }

        .header-divider {
            width: 1px;
            height: 24px;
            background: var(--border);
        }

        .stats-bar {
            display: flex;
            gap: 4px;
            flex: 1;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            color: var(--text-dim);
            background: var(--surface);
            border: 1px solid transparent;
        }

        .stat-chip .sv {
            color: var(--text);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .stat-chip.pass { border-color: rgba(16,185,129,0.3); }
        .stat-chip.pass .sv { color: var(--green); }
        .stat-chip.block { border-color: rgba(239,68,68,0.3); }
        .stat-chip.block .sv { color: var(--red); }

        .controls {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .ctrl-btn {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text-dim);
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .ctrl-btn:hover { border-color: var(--blue); color: var(--text); background: var(--surface2); }
        .ctrl-btn.active { background: var(--blue); border-color: var(--blue); color: #fff; }

        .search-wrap {
            position: relative;
        }

        .search-wrap svg {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: var(--text-muted);
        }

        #search-box {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 5px 10px 5px 28px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 11px;
            width: 160px;
            outline: none;
            transition: all 0.2s;
        }

        #search-box:focus { border-color: var(--blue); width: 200px; }
        #search-box::placeholder { color: var(--text-muted); }

        .live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green);
            animation: livePulse 2s ease-in-out infinite;
        }

        .live-dot.error { background: var(--red); box-shadow: 0 0 8px var(--red); animation: none; }
        .live-dot.loading { background: var(--orange); box-shadow: 0 0 8px var(--orange); }

        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }

        /* ─── Graph ─── */
        #graph-container {
            position: absolute;
            inset: 0;
            padding-top: 52px;
        }

        #graph-container svg { width: 100%; height: 100%; }
        #graph-container canvas { position: absolute; top: 52px; left: 0; pointer-events: none; }

        /* ─── Side panels ─── */
        .panel {
            position: fixed;
            z-index: 90;
            background: rgba(5, 10, 18, 0.92);
            backdrop-filter: blur(20px) saturate(1.5);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 11px;
            overflow: hidden;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s;
        }

        .panel-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-dim);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-body {
            padding: 10px 14px;
            max-height: 400px;
            overflow-y: auto;
        }

        .panel-body::-webkit-scrollbar { width: 4px; }
        .panel-body::-webkit-scrollbar-track { background: transparent; }
        .panel-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        /* ─── Activity Feed ─── */
        #activity-panel {
            top: 62px;
            right: 12px;
            width: 340px;
        }

        #activity-panel.hidden { transform: translateX(360px); opacity: 0; pointer-events: none; }

        .activity-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(26, 39, 68, 0.5);
            animation: fadeSlideIn 0.3s ease-out;
        }

        .activity-row:last-child { border: none; }

        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .act-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .act-time {
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
            font-size: 10px;
            flex-shrink: 0;
            width: 50px;
        }

        .act-flow {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-dim);
        }

        .act-flow .src { color: var(--text); font-weight: 600; }
        .act-flow .dst { color: var(--text); }
        .act-flow .port { color: var(--cyan); font-weight: 500; }

        /* ─── Top Talkers Panel ─── */
        #talkers-panel {
            top: 62px;
            left: 12px;
            width: 280px;
        }

        #talkers-panel.hidden { transform: translateX(-300px); opacity: 0; pointer-events: none; }

        .talker-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
        }

        .talker-tab {
            flex: 1;
            padding: 8px;
            text-align: center;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.15s;
        }

        .talker-tab:hover { color: var(--text-dim); }
        .talker-tab.active { color: var(--cyan); border-bottom-color: var(--cyan); }

        .talker-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
        }

        .talker-rank {
            width: 16px;
            font-weight: 700;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
            font-size: 10px;
            text-align: right;
        }

        .talker-bar-wrap {
            flex: 1;
            position: relative;
            height: 22px;
            background: var(--surface);
            border-radius: 4px;
            overflow: hidden;
        }

        .talker-bar {
            position: absolute;
            inset: 0;
            border-radius: 4px;
            transition: width 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .talker-label {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 500;
            color: var(--text);
            font-size: 10px;
            z-index: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: calc(100% - 40px);
        }

        .talker-count {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 700;
            color: var(--text-dim);
            font-size: 10px;
            z-index: 1;
            font-variant-numeric: tabular-nums;
        }

        /* ─── Legend ─── */
        #legend {
            position: fixed;
            bottom: 16px;
            left: 12px;
            z-index: 90;
        }

        .legend-row {
            display: flex;
            gap: 14px;
            padding: 6px 12px;
            background: rgba(5, 10, 18, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            color: var(--text-dim);
        }

        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .legend-line {
            width: 20px;
            height: 2px;
            border-radius: 1px;
        }

        /* ─── Tooltip ─── */
        #tooltip {
            position: fixed;
            pointer-events: none;
            background: rgba(12, 18, 32, 0.97);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 11px;
            z-index: 300;
            display: none;
            max-width: 350px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6), 0 0 0 1px rgba(59,130,246,0.1);
        }

        .tt-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .tt-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
        }

        .tt-title { font-weight: 700; font-size: 13px; }
        .tt-subtitle { font-size: 10px; color: var(--text-dim); }
        .tt-row { color: var(--text-dim); margin-bottom: 3px; display: flex; justify-content: space-between; }
        .tt-row span { color: var(--text); font-weight: 500; }
        .tt-divider { height: 1px; background: var(--border); margin: 8px 0; }

        /* ─── Filters ─── */
        #filters-panel {
            bottom: 16px;
            right: 12px;
            width: 220px;
        }

        #filters-panel.hidden { transform: translateY(20px); opacity: 0; pointer-events: none; }

        #filters-panel label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 3px 0;
            cursor: pointer;
            color: var(--text-dim);
            transition: color 0.15s;
        }

        #filters-panel label:hover { color: var(--text); }
        #filters-panel input[type="checkbox"] { accent-color: var(--blue); }

        /* ─── Layout mode selector ─── */
        .layout-modes {
            display: flex;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }

        .layout-mode {
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-right: 1px solid var(--border);
            transition: all 0.15s;
        }

        .layout-mode:last-child { border-right: none; }
        .layout-mode:hover { color: var(--text-dim); background: var(--surface2); }
        .layout-mode.active { color: #fff; background: var(--blue); }

        /* ─── Focus mode overlay ─── */
        #focus-info {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: var(--blue);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            z-index: 150;
            display: none;
            cursor: pointer;
        }

        /* ─── Loading ─── */
        #loading {
            position: fixed;
            inset: 0;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 999;
            transition: opacity 0.5s;
        }

        #loading.hidden { opacity: 0; pointer-events: none; }

        .loader-ring {
            width: 48px;
            height: 48px;
            border: 2px solid var(--border);
            border-top-color: var(--green);
            border-right-color: var(--cyan);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        #loading p { margin-top: 16px; color: var(--text-dim); font-size: 12px; font-weight: 500; }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── Error ─── */
        #error-banner {
            position: fixed;
            top: 58px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: var(--red);
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 11px;
            z-index: 150;
            display: none;
            font-weight: 500;
        }

        /* ─── Minimap ─── */
        #minimap {
            position: fixed;
            bottom: 16px;
            right: 12px;
            width: 160px;
            height: 100px;
            background: rgba(5, 10, 18, 0.85);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            z-index: 80;
        }

        #minimap canvas {
            width: 100%;
            height: 100%;
        }

        #minimap .viewport-rect {
            position: absolute;
            border: 1px solid var(--cyan);
            background: rgba(6, 182, 212, 0.08);
            pointer-events: none;
        }
    </style>
</head>
<body>

<div id="loading">
    <div class="loader-ring"></div>
    <p>Connecting to OPNsense...</p>
</div>

<div id="error-banner"></div>

<div id="header">
    <div class="logo">
        <div class="logo-icon">FW</div>
        OPN<span>graph</span>
    </div>

    <div class="header-divider"></div>

    <div class="stats-bar">
        <div class="stat-chip">Aliases <span class="sv" id="stat-aliases">-</span></div>
        <div class="stat-chip">Rules <span class="sv" id="stat-rules">-</span></div>
        <div class="stat-chip">Nodes <span class="sv" id="stat-nodes">-</span></div>
        <div class="stat-chip">Links <span class="sv" id="stat-links">-</span></div>
        <div class="stat-chip pass">Pass <span class="sv" id="stat-pass">-</span></div>
        <div class="stat-chip block">Block <span class="sv" id="stat-block">-</span></div>
        <div class="stat-chip">Logs <span class="sv" id="stat-logs">-</span></div>
    </div>

    <div class="controls">
        <div class="layout-modes">
            <div class="layout-mode active" data-mode="radial">Radial</div>
            <div class="layout-mode" data-mode="force">Force</div>
            <div class="layout-mode" data-mode="hierarchy">Flow</div>
        </div>

        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-box" placeholder="Search nodes...">
        </div>

        <button class="ctrl-btn" id="btn-activity">Activity</button>
        <button class="ctrl-btn" id="btn-talkers">Talkers</button>
        <button class="ctrl-btn" id="btn-filter">Filters</button>
        <button class="ctrl-btn" id="btn-pause">Pause</button>
        <button class="ctrl-btn" id="btn-reset">Reset</button>

        <div class="live-dot" id="status-dot" title="Live"></div>
    </div>
</div>

<!-- Activity Feed -->
<div class="panel hidden" id="activity-panel">
    <div class="panel-header">
        Live Activity
        <span id="activity-count" style="color:var(--cyan)">0</span>
    </div>
    <div class="panel-body" id="activity-body"></div>
</div>

<!-- Top Talkers -->
<div class="panel hidden" id="talkers-panel">
    <div class="talker-tabs">
        <div class="talker-tab active" data-tab="sources">Sources</div>
        <div class="talker-tab" data-tab="destinations">Destinations</div>
        <div class="talker-tab" data-tab="ports">Ports</div>
    </div>
    <div class="panel-body" id="talkers-body"></div>
</div>

<!-- Filters -->
<div class="panel hidden" id="filters-panel">
    <div class="panel-header">Filters</div>
    <div class="panel-body">
        <div style="color:var(--text-muted);font-weight:600;margin-bottom:4px;font-size:10px;text-transform:uppercase;letter-spacing:0.5px">Connections</div>
        <label><input type="checkbox" id="f-green" checked> <span style="color:var(--green)">Permitted (alias source)</span></label>
        <label><input type="checkbox" id="f-orange" checked> <span style="color:var(--orange)">Permitted (unknown source)</span></label>
        <label><input type="checkbox" id="f-red" checked> <span style="color:var(--red)">Blocked / Denied</span></label>
        <div style="height:8px"></div>
        <div style="color:var(--text-muted);font-weight:600;margin-bottom:4px;font-size:10px;text-transform:uppercase;letter-spacing:0.5px">Node Types</div>
        <label><input type="checkbox" id="f-aliases" checked> Aliases</label>
        <label><input type="checkbox" id="f-external" checked> External IPs / Subnets</label>
        <label><input type="checkbox" id="f-internal" checked> Internal IPs</label>
        <label><input type="checkbox" id="f-firewall" checked> Firewall Core</label>
    </div>
</div>

<!-- Legend -->
<div id="legend">
    <div class="legend-row">
        <div class="legend-item"><div class="legend-line" style="background:var(--green)"></div>Alias pass</div>
        <div class="legend-item"><div class="legend-line" style="background:var(--orange)"></div>Unknown pass</div>
        <div class="legend-item"><div class="legend-line" style="background:var(--red)"></div>Blocked</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--green);box-shadow:0 0 6px var(--green)"></div>Firewall</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--blue)"></div>Alias</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--purple)"></div>Subnet</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--text-muted)"></div>IP</div>
    </div>
</div>

<!-- Focus indicator -->
<div id="focus-info">Click to exit focus mode</div>

<!-- Tooltip -->
<div id="tooltip">
    <div class="tt-header">
        <div class="tt-icon"></div>
        <div>
            <div class="tt-title"></div>
            <div class="tt-subtitle"></div>
        </div>
    </div>
    <div class="tt-body"></div>
</div>

<div id="graph-container">
    <canvas id="particle-canvas"></canvas>
</div>

<script>
// ─── Config ───
const POLL_INTERVAL = <?= (int) $pollInterval ?>;
const API_URL = 'api.php';

// ─── State ───
let paused = false;
let pollTimer = null;
let simulation = null;
let currentData = { nodes: [], links: [] };
let svg, g, linkGroup, nodeGroup, labelGroup, ringGroup;
let zoom, currentTransform = d3.zoomIdentity;
let searchTerm = '';
let focusNodeId = null;
let layoutMode = 'radial';
let talkerTab = 'sources';
let particleCanvas, particleCtx;
let particles = [];
let animFrame;

// ─── Colours ───
const C = {
    green:  '#10b981', greenDim: 'rgba(16,185,129,0.15)',
    orange: '#f59e0b', orangeDim: 'rgba(245,158,11,0.15)',
    red:    '#ef4444', redDim: 'rgba(239,68,68,0.15)',
    blue:   '#3b82f6', blueDim: 'rgba(59,130,246,0.2)',
    cyan:   '#06b6d4',
    purple: '#a855f7', purpleDim: 'rgba(168,85,247,0.2)',
    pink:   '#ec4899',
    dim:    '#475569',  dimBg: 'rgba(71,85,105,0.15)',
    fw:     '#10b981',  fwDim: 'rgba(16,185,129,0.2)',
};

function nodeColour(d) {
    if (d.group === 'firewall') return C.fw;
    if (d.group === 'alias')    return C.blue;
    if (d.group === 'internal') return C.cyan;
    if (d.type === 'subnet')    return C.purple;
    return C.dim;
}

function nodeColourDim(d) {
    if (d.group === 'firewall') return C.fwDim;
    if (d.group === 'alias')    return C.blueDim;
    if (d.type === 'subnet')    return C.purpleDim;
    return C.dimBg;
}

function nodeRadius(d) {
    const hits = d.hits || 0;
    const base = d.group === 'firewall' ? 20 : d.group === 'alias' ? 8 : d.type === 'subnet' ? 7 : 5;
    return base + Math.min(Math.sqrt(hits) * 0.5, 12);
}

function linkId(d) {
    const s = typeof d.source === 'object' ? d.source.id : d.source;
    const t = typeof d.target === 'object' ? d.target.id : d.target;
    return `${s}|${t}|${d.colour}`;
}

// ─── Particle System ───
function initParticles() {
    particleCanvas = document.getElementById('particle-canvas');
    const container = document.getElementById('graph-container');
    particleCanvas.width = container.clientWidth;
    particleCanvas.height = container.clientHeight;
    particleCtx = particleCanvas.getContext('2d');
}

function spawnParticles(linkData) {
    if (!linkData || !linkData.length) return;

    // Spawn particles along links
    linkData.forEach(d => {
        if (typeof d.source !== 'object' || typeof d.target !== 'object') return;
        if (Math.random() > 0.15) return; // throttle

        const sx = d.source.x, sy = d.source.y;
        const tx = d.target.x, ty = d.target.y;
        if (sx == null || sy == null || tx == null || ty == null) return;

        particles.push({
            x: sx, y: sy,
            tx: tx, ty: ty,
            progress: 0,
            speed: 0.008 + Math.random() * 0.012,
            colour: C[d.colour] || C.dim,
            size: 1.5 + Math.random() * 1.5,
        });
    });

    // Cap particles
    if (particles.length > 600) {
        particles = particles.slice(-400);
    }
}

function renderParticles() {
    if (!particleCtx) return;

    particleCtx.clearRect(0, 0, particleCanvas.width, particleCanvas.height);

    // Apply current zoom transform
    particleCtx.save();
    particleCtx.translate(currentTransform.x, currentTransform.y);
    particleCtx.scale(currentTransform.k, currentTransform.k);

    const alive = [];
    for (const p of particles) {
        p.progress += p.speed;
        if (p.progress >= 1) continue;

        const t = p.progress;
        // Quadratic bezier for slight curve
        const mx = (p.x + p.tx) / 2 + (p.ty - p.y) * 0.1;
        const my = (p.y + p.ty) / 2 + (p.x - p.tx) * 0.1;
        const cx = (1 - t) * (1 - t) * p.x + 2 * (1 - t) * t * mx + t * t * p.tx;
        const cy = (1 - t) * (1 - t) * p.y + 2 * (1 - t) * t * my + t * t * p.ty;

        const alpha = t < 0.1 ? t / 0.1 : t > 0.8 ? (1 - t) / 0.2 : 1;

        particleCtx.globalAlpha = alpha * 0.8;
        particleCtx.fillStyle = p.colour;
        particleCtx.beginPath();
        particleCtx.arc(cx, cy, p.size, 0, Math.PI * 2);
        particleCtx.fill();

        // Trail
        particleCtx.globalAlpha = alpha * 0.2;
        particleCtx.beginPath();
        particleCtx.arc(cx, cy, p.size * 2.5, 0, Math.PI * 2);
        particleCtx.fill();

        alive.push(p);
    }

    particleCtx.restore();
    particles = alive;
}

function animateLoop() {
    const visibleLinks = linkGroup ? linkGroup.selectAll('path').data() : [];
    spawnParticles(visibleLinks);
    renderParticles();
    animFrame = requestAnimationFrame(animateLoop);
}

// ─── Init SVG ───
function initGraph() {
    const container = document.getElementById('graph-container');
    const width = container.clientWidth;
    const height = container.clientHeight - 52;

    svg = d3.select('#graph-container')
        .append('svg')
        .attr('width', width)
        .attr('height', height);

    const defs = svg.append('defs');

    // Glow filters per colour
    ['green', 'blue', 'purple', 'cyan', 'fw', 'red', 'orange'].forEach(name => {
        const f = defs.append('filter').attr('id', `glow-${name}`).attr('x', '-50%').attr('y', '-50%').attr('width', '200%').attr('height', '200%');
        f.append('feGaussianBlur').attr('stdDeviation', '4').attr('result', 'blur');
        f.append('feComposite').attr('in', 'blur').attr('in2', 'SourceGraphic').attr('operator', 'over');
    });

    // Gradient for links
    ['green', 'orange', 'red'].forEach(colour => {
        const grad = defs.append('linearGradient')
            .attr('id', `grad-${colour}`)
            .attr('gradientUnits', 'userSpaceOnUse');
        grad.append('stop').attr('offset', '0%').attr('stop-color', C[colour]).attr('stop-opacity', 0.8);
        grad.append('stop').attr('offset', '100%').attr('stop-color', C[colour]).attr('stop-opacity', 0.3);
    });

    // Background grid
    const gridSize = 40;
    const grid = defs.append('pattern')
        .attr('id', 'grid')
        .attr('width', gridSize)
        .attr('height', gridSize)
        .attr('patternUnits', 'userSpaceOnUse');
    grid.append('circle').attr('cx', gridSize/2).attr('cy', gridSize/2).attr('r', 0.5).attr('fill', 'rgba(30,50,80,0.4)');

    svg.append('rect').attr('width', '300%').attr('height', '300%').attr('x', '-100%').attr('y', '-100%').attr('fill', 'url(#grid)');

    zoom = d3.zoom()
        .scaleExtent([0.08, 6])
        .on('zoom', (event) => {
            currentTransform = event.transform;
            g.attr('transform', event.transform);
        });

    svg.call(zoom);

    g = svg.append('g');
    ringGroup  = g.append('g').attr('class', 'rings');
    linkGroup  = g.append('g').attr('class', 'links');
    nodeGroup  = g.append('g').attr('class', 'nodes');
    labelGroup = g.append('g').attr('class', 'labels');

    simulation = d3.forceSimulation()
        .force('link', d3.forceLink().id(d => d.id).distance(d => 100).strength(0.3))
        .force('charge', d3.forceManyBody().strength(-200))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(d => nodeRadius(d) + 4))
        .force('x', d3.forceX(width / 2).strength(0.02))
        .force('y', d3.forceY(height / 2).strength(0.02))
        .alphaDecay(0.02)
        .velocityDecay(0.4);

    simulation.on('tick', ticked);

    initParticles();
    animateLoop();
}

// ─── Curved link path generator ───
function linkArc(d) {
    if (typeof d.source !== 'object' || typeof d.target !== 'object') return '';
    const dx = d.target.x - d.source.x;
    const dy = d.target.y - d.source.y;
    const dr = Math.sqrt(dx * dx + dy * dy) * 1.2; // curvature
    return `M${d.source.x},${d.source.y}A${dr},${dr} 0 0,1 ${d.target.x},${d.target.y}`;
}

function ticked() {
    linkGroup.selectAll('path').attr('d', linkArc);

    nodeGroup.selectAll('.node-group').attr('transform', d => `translate(${d.x},${d.y})`);

    labelGroup.selectAll('text')
        .attr('x', d => d.x)
        .attr('y', d => d.y - nodeRadius(d) - 8);
}

// ─── Layout Helpers ───
function applyRadialLayout(nodes, width, height) {
    const cx = width / 2;
    const cy = height / 2;

    const fwNode = nodes.find(n => n.group === 'firewall');
    const aliasNodes = nodes.filter(n => n.group === 'alias');
    const otherNodes = nodes.filter(n => n.group !== 'firewall' && n.group !== 'alias');

    // Firewall at center
    if (fwNode) { fwNode.fx = cx; fwNode.fy = cy; }

    // Aliases in inner ring
    const innerR = Math.min(width, height) * 0.2;
    aliasNodes.forEach((n, i) => {
        const angle = (2 * Math.PI * i) / aliasNodes.length - Math.PI / 2;
        n.x = cx + innerR * Math.cos(angle);
        n.y = cy + innerR * Math.sin(angle);
    });

    // Others in outer ring
    const outerR = Math.min(width, height) * 0.38;
    otherNodes.forEach((n, i) => {
        const angle = (2 * Math.PI * i) / otherNodes.length - Math.PI / 2;
        n.x = cx + outerR * Math.cos(angle);
        n.y = cy + outerR * Math.sin(angle);
    });
}

function applyHierarchyLayout(nodes, width, height) {
    const layers = { firewall: [], alias: [], internal: [], external: [] };

    nodes.forEach(n => {
        if (n.group === 'firewall') layers.firewall.push(n);
        else if (n.group === 'alias') layers.alias.push(n);
        else if (n.group === 'internal') layers.internal.push(n);
        else layers.external.push(n);
    });

    const yPositions = { firewall: height * 0.15, alias: height * 0.38, internal: height * 0.62, external: height * 0.85 };

    Object.entries(layers).forEach(([key, layer]) => {
        const y = yPositions[key];
        layer.forEach((n, i) => {
            n.x = (width / (layer.length + 1)) * (i + 1);
            n.y = y;
        });
    });

    const fw = layers.firewall[0];
    if (fw) { fw.fx = width / 2; fw.fy = yPositions.firewall; }
}

function applyForceLayout(nodes, width, height) {
    const fwNode = nodes.find(n => n.group === 'firewall');
    if (fwNode) { fwNode.fx = width / 2; fwNode.fy = height / 2; }
}

// ─── Filters ───
function getActiveFilters() {
    return {
        green:    document.getElementById('f-green').checked,
        orange:   document.getElementById('f-orange').checked,
        red:      document.getElementById('f-red').checked,
        aliases:  document.getElementById('f-aliases').checked,
        external: document.getElementById('f-external').checked,
        internal: document.getElementById('f-internal').checked,
        firewall: document.getElementById('f-firewall').checked,
    };
}

function shouldShowNode(d, filters) {
    if (d.group === 'firewall' && !filters.firewall) return false;
    if (d.group === 'alias' && !filters.aliases) return false;
    if (d.group === 'external' && !filters.external) return false;
    if (d.group === 'internal' && !filters.internal) return false;
    if (searchTerm && !d.label.toLowerCase().includes(searchTerm)) return false;
    return true;
}

function shouldShowLink(d, filters) {
    return filters[d.colour] !== false;
}

// ─── Focus Mode ───
function setFocus(nodeId) {
    focusNodeId = nodeId;
    const info = document.getElementById('focus-info');

    if (!nodeId) {
        info.style.display = 'none';
        // Restore all opacity
        nodeGroup.selectAll('.node-group').transition().duration(300).attr('opacity', 1);
        linkGroup.selectAll('path').transition().duration(300).attr('opacity', 0.4);
        labelGroup.selectAll('text').transition().duration(300).attr('opacity', 1);
        return;
    }

    info.style.display = 'block';

    // Find connected nodes
    const connected = new Set([nodeId]);
    currentData.links.forEach(l => {
        const s = typeof l.source === 'object' ? l.source.id : l.source;
        const t = typeof l.target === 'object' ? l.target.id : l.target;
        if (s === nodeId) connected.add(t);
        if (t === nodeId) connected.add(s);
    });

    nodeGroup.selectAll('.node-group').transition().duration(300)
        .attr('opacity', d => connected.has(d.id) ? 1 : 0.06);

    linkGroup.selectAll('path').transition().duration(300)
        .attr('opacity', d => {
            const s = typeof d.source === 'object' ? d.source.id : d.source;
            const t = typeof d.target === 'object' ? d.target.id : d.target;
            return (s === nodeId || t === nodeId) ? 0.7 : 0.02;
        });

    labelGroup.selectAll('text').transition().duration(300)
        .attr('opacity', d => connected.has(d.id) ? 1 : 0.05);
}

// ─── Update Graph ───
function updateGraph(data) {
    const filters = getActiveFilters();
    const container = document.getElementById('graph-container');
    const width = container.clientWidth;
    const height = container.clientHeight - 52;

    // Merge positions from existing nodes
    const oldMap = {};
    currentData.nodes.forEach(n => { oldMap[n.id] = n; });

    data.nodes.forEach(n => {
        if (oldMap[n.id]) {
            n.x = oldMap[n.id].x;
            n.y = oldMap[n.id].y;
            n.vx = oldMap[n.id].vx;
            n.vy = oldMap[n.id].vy;
            n.fx = oldMap[n.id].fx;
            n.fy = oldMap[n.id].fy;
        }
    });

    const isFirst = currentData.nodes.length === 0;
    currentData = data;

    // Apply layout on first load
    if (isFirst) {
        if (layoutMode === 'radial') applyRadialLayout(data.nodes, width, height);
        else if (layoutMode === 'hierarchy') applyHierarchyLayout(data.nodes, width, height);
        else applyForceLayout(data.nodes, width, height);
    }

    // Filter nodes and links
    const visibleNodeIds = new Set();
    const visibleNodes = data.nodes.filter(n => {
        const show = shouldShowNode(n, filters);
        if (show) visibleNodeIds.add(n.id);
        return show;
    });

    const visibleLinks = data.links.filter(l => {
        const srcId = typeof l.source === 'object' ? l.source.id : l.source;
        const tgtId = typeof l.target === 'object' ? l.target.id : l.target;
        return shouldShowLink(l, filters) && visibleNodeIds.has(srcId) && visibleNodeIds.has(tgtId);
    });

    // ── Links (curved paths) ──
    const link = linkGroup.selectAll('path').data(visibleLinks, linkId);

    link.exit().transition().duration(400).attr('stroke-opacity', 0).remove();

    const linkEnter = link.enter().append('path')
        .attr('fill', 'none')
        .attr('stroke', d => C[d.colour])
        .attr('stroke-opacity', 0)
        .attr('stroke-width', d => Math.max(0.8, Math.min(1 + Math.log2(d.count), 4)))
        .attr('stroke-dasharray', d => d.colour === 'red' ? '4,3' : 'none')
        .attr('opacity', 0.4)
        .on('mouseover', (event, d) => showLinkTooltip(event, d))
        .on('mouseout', hideTooltip);

    linkEnter.transition().duration(600).attr('stroke-opacity', 0.5);

    link.merge(linkEnter)
        .attr('stroke-width', d => Math.max(0.8, Math.min(1 + Math.log2(d.count), 4)));

    // ── Nodes ──
    const node = nodeGroup.selectAll('.node-group').data(visibleNodes, d => d.id);

    node.exit().transition().duration(400).attr('opacity', 0).remove();

    const nodeEnter = node.enter().append('g')
        .attr('class', 'node-group')
        .attr('cursor', 'pointer')
        .attr('opacity', 0)
        .on('click', (event, d) => {
            event.stopPropagation();
            setFocus(focusNodeId === d.id ? null : d.id);
        })
        .on('mouseover', (event, d) => {
            if (!focusNodeId) {
                // Highlight on hover
                d3.select(event.currentTarget).select('.node-ring')
                    .transition().duration(200).attr('r', nodeRadius(d) + 8).attr('opacity', 0.3);
            }
            showNodeTooltip(event, d);
        })
        .on('mouseout', (event, d) => {
            d3.select(event.currentTarget).select('.node-ring')
                .transition().duration(300).attr('r', nodeRadius(d) + 4).attr('opacity', d.group === 'firewall' ? 0.2 : 0);
            hideTooltip();
        })
        .call(drag(simulation));

    // Outer ring (hover/pulse effect)
    nodeEnter.append('circle')
        .attr('class', 'node-ring')
        .attr('r', d => nodeRadius(d) + 4)
        .attr('fill', 'none')
        .attr('stroke', d => nodeColour(d))
        .attr('stroke-width', 1)
        .attr('opacity', d => d.group === 'firewall' ? 0.2 : 0);

    // Main circle
    nodeEnter.append('circle')
        .attr('class', 'node-core')
        .attr('r', d => nodeRadius(d))
        .attr('fill', d => nodeColourDim(d))
        .attr('stroke', d => nodeColour(d))
        .attr('stroke-width', d => d.group === 'firewall' ? 2.5 : 1.5);

    // Icon text inside node
    nodeEnter.append('text')
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'central')
        .attr('font-size', d => d.group === 'firewall' ? '9px' : '7px')
        .attr('font-weight', '700')
        .attr('fill', d => nodeColour(d))
        .attr('pointer-events', 'none')
        .text(d => {
            if (d.group === 'firewall') return 'FW';
            if (d.type === 'subnet') return d.members ? d.members.length : 'S';
            return '';
        });

    nodeEnter.transition().duration(600).attr('opacity', 1);

    // Update existing
    node.merge(nodeEnter).select('.node-core')
        .transition().duration(500)
        .attr('r', d => nodeRadius(d))
        .attr('fill', d => {
            if (searchTerm && d.label.toLowerCase().includes(searchTerm)) return nodeColour(d);
            if (searchTerm && !d.label.toLowerCase().includes(searchTerm)) return 'rgba(30,40,60,0.3)';
            return nodeColourDim(d);
        })
        .attr('stroke', d => {
            if (searchTerm && d.label.toLowerCase().includes(searchTerm)) return '#fff';
            return nodeColour(d);
        });

    // ── Labels ──
    const label = labelGroup.selectAll('text').data(visibleNodes, d => d.id);

    label.exit().remove();

    const labelEnter = label.enter().append('text')
        .attr('text-anchor', 'middle')
        .attr('fill', d => d.group === 'alias' ? 'rgba(148,163,184,0.9)' : 'rgba(100,116,139,0.7)')
        .attr('font-size', d => d.group === 'firewall' ? '12px' : d.group === 'alias' ? '10px' : '8px')
        .attr('font-weight', d => d.group === 'firewall' || d.group === 'alias' ? '600' : '400')
        .attr('pointer-events', 'none')
        .text(d => {
            if (d.label.length > 22) return d.label.substring(0, 20) + '...';
            return d.label;
        });

    label.merge(labelEnter)
        .attr('fill', d => {
            if (searchTerm && d.label.toLowerCase().includes(searchTerm)) return '#fff';
            if (searchTerm) return 'rgba(100,116,139,0.2)';
            return d.group === 'alias' ? 'rgba(148,163,184,0.9)' : 'rgba(100,116,139,0.7)';
        });

    // ── Simulation ──
    simulation.nodes(visibleNodes);
    simulation.force('link').links(visibleLinks);

    // Tune forces based on layout
    if (layoutMode === 'radial') {
        simulation.force('charge').strength(-150);
        simulation.force('link').distance(80).strength(0.2);
    } else if (layoutMode === 'hierarchy') {
        simulation.force('charge').strength(-100);
        simulation.force('link').distance(60).strength(0.4);
    } else {
        simulation.force('charge').strength(-250);
        simulation.force('link').distance(100).strength(0.3);
    }

    simulation.alpha(isFirst ? 0.8 : 0.15).restart();

    // Re-apply focus if active
    if (focusNodeId) setFocus(focusNodeId);

    // ── Stats ──
    document.getElementById('stat-aliases').textContent = data.aliasCount ?? '-';
    document.getElementById('stat-rules').textContent = data.ruleCount ?? '-';
    document.getElementById('stat-nodes').textContent = visibleNodes.length;
    document.getElementById('stat-links').textContent = visibleLinks.length;
    document.getElementById('stat-pass').textContent = data.passCount ?? '-';
    document.getElementById('stat-block').textContent = data.blockCount ?? '-';
    document.getElementById('stat-logs').textContent = data.logCount ?? '-';

    // ── Activity Feed ──
    updateActivityFeed(data.recent || []);

    // ── Top Talkers ──
    updateTalkers(data);
}

// ─── Activity Feed ───
function updateActivityFeed(recent) {
    const body = document.getElementById('activity-body');
    document.getElementById('activity-count').textContent = recent.length;

    body.innerHTML = recent.map(r => {
        const dotColour = C[r.colour] || C.dim;
        const time = r.time ? r.time.split('T')[1]?.substring(0, 8) || r.time : '';
        const portStr = r.portName ? `${r.port} (${r.portName})` : r.port;
        return `<div class="activity-row">
            <div class="act-dot" style="background:${dotColour}"></div>
            <div class="act-time">${time}</div>
            <div class="act-flow">
                <span class="src">${escHtml(r.src)}</span> &rarr; <span class="dst">${escHtml(r.dst)}</span>${portStr ? ` :<span class="port">${escHtml(portStr)}</span>` : ''}
            </div>
        </div>`;
    }).join('');
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ─── Top Talkers ───
function updateTalkers(data) {
    const body = document.getElementById('talkers-body');
    let items = {};
    let barColour = C.cyan;

    if (talkerTab === 'sources') {
        items = data.topSources || {};
        barColour = 'rgba(6,182,212,0.25)';
    } else if (talkerTab === 'destinations') {
        items = data.topDests || {};
        barColour = 'rgba(168,85,247,0.25)';
    } else if (talkerTab === 'ports') {
        items = data.topPorts || {};
        barColour = 'rgba(245,158,11,0.25)';
    }

    const entries = Object.entries(items);
    if (!entries.length) { body.innerHTML = '<div style="color:var(--text-muted);padding:10px">No data</div>'; return; }

    const maxVal = entries[0]?.[1] || 1;

    body.innerHTML = entries.map(([label, count], i) => {
        const pct = (count / maxVal) * 100;
        return `<div class="talker-row">
            <div class="talker-rank">${i + 1}</div>
            <div class="talker-bar-wrap">
                <div class="talker-bar" style="width:${pct}%;background:${barColour}"></div>
                <div class="talker-label">${escHtml(label)}</div>
                <div class="talker-count">${count}</div>
            </div>
        </div>`;
    }).join('');
}

// ─── Tooltip ───
function showNodeTooltip(event, d) {
    const tt = document.getElementById('tooltip');
    const icon = tt.querySelector('.tt-icon');
    const title = tt.querySelector('.tt-title');
    const subtitle = tt.querySelector('.tt-subtitle');
    const body = tt.querySelector('.tt-body');

    const col = nodeColour(d);
    icon.style.background = nodeColourDim(d);
    icon.style.color = col;
    icon.style.border = `1px solid ${col}`;
    icon.textContent = d.group === 'firewall' ? 'FW' : d.group === 'alias' ? 'A' : d.type === 'subnet' ? 'S' : 'IP';

    title.textContent = d.label;
    subtitle.textContent = d.title || d.group;

    let html = '';
    html += `<div class="tt-row"><span>Type</span><span>${d.group}${d.aliasType ? ' (' + d.aliasType + ')' : ''}</span></div>`;
    html += `<div class="tt-row"><span>Hits</span><span>${d.hits || 0}</span></div>`;

    if (d.members) {
        html += `<div class="tt-row"><span>IPs in subnet</span><span>${d.members.length}</span></div>`;
        html += `<div class="tt-divider"></div>`;
        html += `<div style="color:var(--text-muted);font-size:10px">${d.members.slice(0, 8).join(', ')}${d.members.length > 8 ? ', ...' : ''}</div>`;
    }

    if (d.content) {
        html += `<div class="tt-divider"></div>`;
        const items = d.content.split('\n').filter(Boolean).slice(0, 6);
        html += `<div style="color:var(--text-muted);font-size:10px">${items.join(', ')}${items.length >= 6 ? ', ...' : ''}</div>`;
    }

    // Connection summary
    const incoming = currentData.links.filter(l => (typeof l.target === 'object' ? l.target.id : l.target) === d.id);
    const outgoing = currentData.links.filter(l => (typeof l.source === 'object' ? l.source.id : l.source) === d.id);

    if (incoming.length || outgoing.length) {
        html += `<div class="tt-divider"></div>`;
        html += `<div class="tt-row"><span>Incoming</span><span>${incoming.length} connections</span></div>`;
        html += `<div class="tt-row"><span>Outgoing</span><span>${outgoing.length} connections</span></div>`;

        const totalHits = [...incoming, ...outgoing].reduce((sum, l) => sum + l.count, 0);
        html += `<div class="tt-row"><span>Total hits</span><span>${totalHits}</span></div>`;
    }

    body.innerHTML = html;
    positionTooltip(event, tt);
}

function showLinkTooltip(event, d) {
    const tt = document.getElementById('tooltip');
    const icon = tt.querySelector('.tt-icon');
    const title = tt.querySelector('.tt-title');
    const subtitle = tt.querySelector('.tt-subtitle');
    const body = tt.querySelector('.tt-body');

    const col = C[d.colour];
    icon.style.background = C[d.colour + 'Dim'] || 'rgba(100,116,139,0.2)';
    icon.style.color = col;
    icon.style.border = `1px solid ${col}`;
    icon.textContent = d.action === 'block' ? 'X' : '>';

    const srcLabel = typeof d.source === 'object' ? d.source.label : d.source;
    const tgtLabel = typeof d.target === 'object' ? d.target.label : d.target;

    title.textContent = `${srcLabel} → ${tgtLabel}`;
    subtitle.textContent = d.action || 'pass';

    let html = `<div class="tt-row"><span>Action</span><span style="color:${col}">${d.action || 'pass'}</span></div>`;
    html += `<div class="tt-row"><span>Protocol</span><span>${d.protocol || 'any'}</span></div>`;
    html += `<div class="tt-row"><span>Hits</span><span>${d.count}</span></div>`;

    if (d.ports?.length) {
        const portLabels = d.ports.map(p => d.portNames?.[p] ? `${p} (${d.portNames[p]})` : p);
        html += `<div class="tt-divider"></div>`;
        html += `<div class="tt-row"><span>Ports</span><span>${portLabels.join(', ')}</span></div>`;
    }

    body.innerHTML = html;
    positionTooltip(event, tt);
}

function positionTooltip(event, tt) {
    tt.style.display = 'block';
    const rect = tt.getBoundingClientRect();
    let x = event.clientX + 16;
    let y = event.clientY - 16;
    if (x + 350 > window.innerWidth) x = event.clientX - 360;
    if (y + rect.height > window.innerHeight) y = window.innerHeight - rect.height - 8;
    if (y < 56) y = 56;
    tt.style.left = x + 'px';
    tt.style.top = y + 'px';
}

function hideTooltip() {
    document.getElementById('tooltip').style.display = 'none';
}

// ─── Drag ───
function drag(sim) {
    return d3.drag()
        .on('start', (event, d) => {
            if (!event.active) sim.alphaTarget(0.2).restart();
            d.fx = d.x;
            d.fy = d.y;
        })
        .on('drag', (event, d) => {
            d.fx = event.x;
            d.fy = event.y;
        })
        .on('end', (event, d) => {
            if (!event.active) sim.alphaTarget(0);
            // Keep pinned in radial mode, release in force mode
            if (layoutMode === 'force') {
                d.fx = null;
                d.fy = null;
            }
        });
}

// ─── API ───
async function fetchGraphData() {
    const dot = document.getElementById('status-dot');
    dot.className = 'live-dot loading';

    try {
        const resp = await fetch(`${API_URL}?action=graph&_t=${Date.now()}`);
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        if (data.error) throw new Error(data.error);

        dot.className = 'live-dot';
        document.getElementById('error-banner').style.display = 'none';
        return data;
    } catch (err) {
        dot.className = 'live-dot error';
        const banner = document.getElementById('error-banner');
        banner.textContent = `Connection error: ${err.message}`;
        banner.style.display = 'block';
        return null;
    }
}

async function poll() {
    if (paused) return;
    const data = await fetchGraphData();
    if (data) updateGraph(data);
}

// ─── UI Events ───

// Pause
document.getElementById('btn-pause').addEventListener('click', function () {
    paused = !paused;
    this.textContent = paused ? 'Resume' : 'Pause';
    this.classList.toggle('active', paused);
    if (!paused) poll();
});

// Reset view
document.getElementById('btn-reset').addEventListener('click', () => {
    const container = document.getElementById('graph-container');
    svg.transition().duration(750).call(
        zoom.transform,
        d3.zoomIdentity
    );
    setFocus(null);
});

// Activity panel toggle
document.getElementById('btn-activity').addEventListener('click', function () {
    document.getElementById('activity-panel').classList.toggle('hidden');
    this.classList.toggle('active');
});

// Talkers panel toggle
document.getElementById('btn-talkers').addEventListener('click', function () {
    document.getElementById('talkers-panel').classList.toggle('hidden');
    this.classList.toggle('active');
});

// Filter panel toggle
document.getElementById('btn-filter').addEventListener('click', function () {
    document.getElementById('filters-panel').classList.toggle('hidden');
    this.classList.toggle('active');
});

// Filter changes
document.querySelectorAll('#filters-panel input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => {
        if (currentData.nodes.length) updateGraph(currentData);
    });
});

// Search
document.getElementById('search-box').addEventListener('input', function () {
    searchTerm = this.value.toLowerCase().trim();
    if (currentData.nodes.length) updateGraph(currentData);
});

// Escape to clear
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('search-box').value = '';
        searchTerm = '';
        setFocus(null);
        if (currentData.nodes.length) updateGraph(currentData);
    }
});

// Focus mode click-off
document.getElementById('focus-info').addEventListener('click', () => setFocus(null));
svg?.on('click', () => { if (focusNodeId) setFocus(null); });

// Layout mode switcher
document.querySelectorAll('.layout-mode').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.layout-mode').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        layoutMode = this.dataset.mode;

        // Unpin all nodes first
        currentData.nodes.forEach(n => { n.fx = null; n.fy = null; });

        const container = document.getElementById('graph-container');
        const w = container.clientWidth;
        const h = container.clientHeight - 52;

        if (layoutMode === 'radial') applyRadialLayout(currentData.nodes, w, h);
        else if (layoutMode === 'hierarchy') applyHierarchyLayout(currentData.nodes, w, h);
        else applyForceLayout(currentData.nodes, w, h);

        simulation.alpha(0.8).restart();
    });
});

// Talker tabs
document.querySelectorAll('.talker-tab').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.talker-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        talkerTab = this.dataset.tab;
        if (currentData) updateTalkers(currentData);
    });
});

// Resize
window.addEventListener('resize', () => {
    const container = document.getElementById('graph-container');
    const w = container.clientWidth;
    const h = container.clientHeight - 52;
    svg.attr('width', w).attr('height', h);
    particleCanvas.width = w;
    particleCanvas.height = h;
    simulation.force('center', d3.forceCenter(w / 2, h / 2));
    simulation.force('x', d3.forceX(w / 2).strength(0.02));
    simulation.force('y', d3.forceY(h / 2).strength(0.02));
    simulation.alpha(0.2).restart();
});

// ─── Boot ───
(async function init() {
    initGraph();

    // Click on SVG background clears focus
    svg.on('click', (event) => {
        if (event.target.tagName === 'svg' || event.target.tagName === 'rect') {
            if (focusNodeId) setFocus(null);
        }
    });

    const data = await fetchGraphData();
    document.getElementById('loading').classList.add('hidden');

    if (data) updateGraph(data);

    pollTimer = setInterval(poll, POLL_INTERVAL);
})();
</script>

</body>
</html>
