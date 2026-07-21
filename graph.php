<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$pageTitle = 'Graph View';
$active = 'graph';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">Graph View</div>
    <div class="page-sub">Every note, and how it connects to the rest of your vault.</div>
  </div>
</div>

<div class="panel" style="padding:0; overflow:hidden;">
  <div id="graph-empty" class="empty" style="display:none; padding:60px 0;">
    Not enough connected notes yet. Add shared tags between a few notes to see them linked here.
  </div>
  <svg id="graph-svg" width="100%" height="640" style="display:block; background:var(--cream-dim);"></svg>
</div>

<div class="panel" style="margin-top:16px; font-size:12.5px; color:var(--ink-dim); display:flex; gap:18px; align-items:center; flex-wrap:wrap;">
  <span style="font-family:var(--mono); text-transform:uppercase; letter-spacing:.06em; font-size:10.5px;">Legend</span>
  <span>🖱 Drag nodes to rearrange</span>
  <span>🖱 Click a node to open that note</span>
  <span>⚲ Scroll to zoom</span>
  <span>Node size = number of connections</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
<script>
(async function () {
  const container = document.getElementById('graph-svg');
  const emptyState = document.getElementById('graph-empty');
  const width = container.clientWidth || 800;
  const height = 640;

  let payload;
  try {
    const res = await fetch('<?= BASE_URL ?>/api/graph-data.php');
    payload = await res.json();
  } catch (e) {
    emptyState.textContent = 'Could not load graph data.';
    emptyState.style.display = 'block';
    container.style.display = 'none';
    return;
  }

  const { nodes, links } = payload;

  if (!nodes.length) {
    emptyState.style.display = 'block';
    container.style.display = 'none';
    return;
  }

  // degree = number of connections, drives node radius
  const degree = {};
  nodes.forEach(n => degree[n.id] = 0);
  links.forEach(l => { degree[l.source] = (degree[l.source]||0)+1; degree[l.target] = (degree[l.target]||0)+1; });

  const svg = d3.select('#graph-svg').attr('viewBox', [0, 0, width, height]);
  const g = svg.append('g');

  svg.call(d3.zoom().scaleExtent([0.3, 3]).on('zoom', (event) => {
    g.attr('transform', event.transform);
  }));

  const simulation = d3.forceSimulation(nodes)
    .force('link', d3.forceLink(links).id(d => d.id).distance(90).strength(0.5))
    .force('charge', d3.forceManyBody().strength(-220))
    .force('center', d3.forceCenter(width / 2, height / 2))
    .force('collide', d3.forceCollide(d => 12 + (degree[d.id]||0) * 2));

  const link = g.append('g')
    .selectAll('line')
    .data(links)
    .join('line')
    .attr('stroke', '#8FA998')
    .attr('stroke-width', 1.5)
    .attr('stroke-opacity', 0.6);

  const linkLabel = g.append('g')
    .selectAll('text')
    .data(links)
    .join('text')
    .text(d => d.reason)
    .attr('font-size', 9)
    .attr('font-family', 'monospace')
    .attr('fill', '#4C5F55')
    .attr('opacity', 0)
    .style('pointer-events', 'none');

  const node = g.append('g')
    .selectAll('circle')
    .data(nodes)
    .join('circle')
    .attr('r', d => 9 + (degree[d.id]||0) * 2.2)
    .attr('fill', d => d.color || '#0A3323')
    .attr('stroke', '#F7F4D5')
    .attr('stroke-width', 2)
    .style('cursor', 'pointer')
    .call(drag(simulation))
    .on('click', (event, d) => {
      window.location.href = '<?= BASE_URL ?>/notes/view.php?id=' + d.id;
    })
    .on('mouseenter', function (event, d) {
      d3.select(this).attr('stroke', '#C9A24B').attr('stroke-width', 3);
      linkLabel.filter(l => l.source.id === d.id || l.target.id === d.id).attr('opacity', 1);
    })
    .on('mouseleave', function () {
      d3.select(this).attr('stroke', '#F7F4D5').attr('stroke-width', 2);
      linkLabel.attr('opacity', 0);
    });

  node.append('title').text(d => d.title);

  const label = g.append('g')
    .selectAll('text')
    .data(nodes)
    .join('text')
    .text(d => d.title.length > 22 ? d.title.slice(0, 22) + '…' : d.title)
    .attr('font-size', 11)
    .attr('font-family', 'Inter, sans-serif')
    .attr('fill', '#122019')
    .attr('dx', 14)
    .attr('dy', 4)
    .style('pointer-events', 'none');

  simulation.on('tick', () => {
    link
      .attr('x1', d => d.source.x).attr('y1', d => d.source.y)
      .attr('x2', d => d.target.x).attr('y2', d => d.target.y);
    linkLabel
      .attr('x', d => (d.source.x + d.target.x) / 2)
      .attr('y', d => (d.source.y + d.target.y) / 2);
    node.attr('cx', d => d.x).attr('cy', d => d.y);
    label.attr('x', d => d.x).attr('y', d => d.y);
  });

  function drag(simulation) {
    function dragstarted(event, d) {
      if (!event.active) simulation.alphaTarget(0.3).restart();
      d.fx = d.x; d.fy = d.y;
    }
    function dragged(event, d) { d.fx = event.x; d.fy = event.y; }
    function dragended(event, d) {
      if (!event.active) simulation.alphaTarget(0);
      d.fx = null; d.fy = null;
    }
    return d3.drag().on('start', dragstarted).on('drag', dragged).on('end', dragended);
  }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
