<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PC³ — Resultados</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; font-size: 14px; line-height: 1.5; }

.header { background: #1e293b; color: #fff; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; }
.header h1 { font-size: 1rem; font-weight: 600; letter-spacing: 0.01em; }
.stats { display: flex; gap: 1rem; font-size: 0.8rem; color: #94a3b8; flex-wrap: wrap; }
.stats span { display: flex; align-items: center; gap: 0.3rem; }

.nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 0 1.5rem; display: flex; }
.nav a { padding: 0.7rem 1.1rem; text-decoration: none; color: #64748b; font-size: 0.85rem; border-bottom: 2px solid transparent; display: inline-block; margin-bottom: -1px; }
.nav a.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 500; }
.nav a:hover:not(.active) { color: #334155; }

.content { padding: 1.25rem 1.5rem; }

/* badges */
.badge { display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
.Predicate { background: #dbeafe; color: #1d4ed8; }
.Concept   { background: #fef9c3; color: #a16207; }
.Context   { background: #dcfce7; color: #166534; }
.code-badge { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.provider-chip { display: inline-block; padding: 0.1rem 0.4rem; border-radius: 3px; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
.anthropic { background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; }
.openai    { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.gemini    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.deepseek  { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

/* table */
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 1.25rem; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 800px; }
th { background: #f8fafc; padding: 0.6rem 0.9rem; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; border-bottom: 1px solid #e2e8f0; text-align: left; white-space: nowrap; }
td { padding: 0.7rem 0.9rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }

.ts  { font-size: 0.75rem; color: #475569; white-space: nowrap; }
.rid { font-size: 0.65rem; color: #94a3b8; font-family: monospace; }
.meta { font-size: 0.72rem; color: #94a3b8; margin-top: 0.2rem; }

details { margin-top: 0.35rem; }
summary { font-size: 0.72rem; color: #94a3b8; cursor: pointer; user-select: none; list-style: none; display: flex; align-items: center; gap: 0.25rem; }
summary::before { content: '▶'; font-size: 0.55rem; transition: transform 0.15s; }
details[open] summary::before { transform: rotate(90deg); }
summary:hover { color: #475569; }
.detail-box { margin-top: 0.4rem; padding: 0.6rem 0.75rem; background: #f8fafc; border-left: 3px solid #e2e8f0; border-radius: 0 4px 4px 0; font-size: 0.78rem; color: #475569; line-height: 1.55; }
.detail-box strong { color: #334155; }

.section-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.5rem; }
.section-count { font-size: 0.75rem; color: #94a3b8; font-weight: 400; }

.empty { padding: 3rem; text-align: center; color: #94a3b8; font-size: 0.9rem; }

.stat-cats { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.group-header { display: flex; align-items: baseline; gap: 0.6rem; margin-bottom: 0.4rem; }
.group-num { font-size: 0.85rem; font-weight: 600; color: #1e293b; }
.group-meta { font-size: 0.75rem; color: #94a3b8; }
.call-num { font-size: 0.8rem; font-weight: 700; color: #334155; }
</style>
</head>
<body>

<div class="header">
    <h1>PC³ — Resultados Diagnósticos</h1>
    <div class="stats">
        <span>{{ $stats['total'] }} resultados</span>
        <span>·</span>
        <span>{{ $stats['exercises'] }} chamadas</span>
        <span>·</span>
        @foreach ($stats['by_category'] as $cat => $n)
            <span><span class="badge {{ $cat }}">{{ $cat }}</span> {{ $n }}</span>
        @endforeach
    </div>
</div>

<div class="nav">
    <a href="?view=exercise" class="{{ $view === 'exercise' ? 'active' : '' }}">Por exercício</a>
    <a href="?view=llm" class="{{ $view === 'llm' ? 'active' : '' }}">Por LLM</a>
</div>

<div class="content">

@if ($view === 'exercise')

    @if ($exerciseGroups->isEmpty())
        <div class="card"><div class="empty">Nenhum resultado encontrado.</div></div>
    @else
    @foreach ($exerciseGroups as $gi => $group)
        @php
            $num   = $exerciseGroups->count() - $gi;
            $first = $group->first()['created_at'];
            $last  = $group->last()['created_at'];
            $range = $first->format('d/m H:i') . ($group->count() > 1 ? ' – ' . $last->format('H:i') : '');
        @endphp
        <div class="group-header">
            <span class="group-num">Exercício {{ $num }}</span>
            <span class="group-meta">{{ $range }} · {{ $group->count() }} {{ $group->count() === 1 ? 'chamada' : 'chamadas' }}</span>
        </div>
        <div class="card" style="margin-bottom:1.5rem">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:100px">Chamada</th>
                            <th>Anthropic</th>
                            <th>OpenAI</th>
                            <th>Gemini</th>
                            <th>DeepSeek</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($group as $ci => $call)
                        @php $prov = $call['providers']; @endphp
                        <tr>
                            <td>
                                <div class="call-num">#{{ $ci + 1 }}</div>
                                <div class="ts">{{ $call['created_at']->format('H:i:s') }}</div>
                                <div class="rid">{{ substr($call['request_id'], 0, 8) }}…</div>
                            </td>
                            @foreach (['anthropic', 'openai', 'gemini', 'deepseek'] as $p)
                            <td>
                                @if (isset($prov[$p]))
                                    @php $r = $prov[$p]; @endphp
                                    <span class="badge {{ $r->pc3_category->value }}">{{ $r->pc3_category->value }}</span>
                                    <span class="badge code-badge">{{ $r->error_code->value }}</span>
                                    <div class="meta">{{ number_format($r->confidence * 100, 0) }}% conf · {{ $r->latency_ms }}ms</div>
                                    <div class="meta">{{ $r->tokens_input }}↑ {{ $r->tokens_output }}↓ tokens</div>
                                    <details>
                                        <summary>diagnóstico</summary>
                                        <div class="detail-box">
                                            <strong>Diagnóstico:</strong><br>{{ $r->diagnosis }}<br><br>
                                            <strong>Feedback:</strong><br>{{ $r->feedback }}
                                        </div>
                                    </details>
                                @else
                                    <span style="color:#cbd5e1">—</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
    @endif

@else {{-- por LLM --}}

    @foreach (['anthropic', 'openai', 'gemini', 'deepseek'] as $provider)
        @php $rows = $byLlm->get($provider, collect()); @endphp
        <div class="section-title">
            <span class="provider-chip {{ $provider }}">{{ $provider }}</span>
            <span class="section-count">{{ $rows->count() }} resultados</span>
        </div>

        @if ($rows->isEmpty())
            <div class="card"><div class="empty" style="padding:1rem">Sem dados.</div></div>
        @else
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:120px">Quando</th>
                            <th>Categoria</th>
                            <th>Código</th>
                            <th>Confiança</th>
                            <th>Latência</th>
                            <th>Tokens ↑</th>
                            <th>Tokens ↓</th>
                            <th>Diagnóstico</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>
                                <div class="ts">{{ $r->created_at->format('d/m H:i:s') }}</div>
                                <div class="rid">{{ substr($r->request_id, 0, 8) }}…</div>
                            </td>
                            <td><span class="badge {{ $r->pc3_category->value }}">{{ $r->pc3_category->value }}</span></td>
                            <td><span class="badge code-badge">{{ $r->error_code->value }}</span></td>
                            <td>{{ number_format($r->confidence * 100, 0) }}%</td>
                            <td>{{ $r->latency_ms }}ms</td>
                            <td>{{ $r->tokens_input }}</td>
                            <td>{{ $r->tokens_output }}</td>
                            <td>
                                <details>
                                    <summary>ver</summary>
                                    <div class="detail-box">
                                        <strong>Diagnóstico:</strong><br>{{ $r->diagnosis }}<br><br>
                                        <strong>Feedback:</strong><br>{{ $r->feedback }}
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach

@endif
</div>
</body>
</html>
