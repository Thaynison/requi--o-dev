// Configurações da API
const API_BASE_URL = 'http://localhost:3000/backend/api';

// Estado da aplicação
let currentUser = null;
let editingReqId = null;

// Utilitários
const money = n => (n||0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
const fmtDate = s => s ? new Date(s).toLocaleDateString('pt-BR') : '—';
const nowISO = () => new Date().toISOString();

// Funções de API
async function apiRequest(endpoint, options = {}) {
  const url = `${API_BASE_URL}/${endpoint}`;
  const defaultOptions = {
    headers: {
      'Content-Type': 'application/json',
      ...options.headers
    }
  };
  
  const response = await fetch(url, { ...defaultOptions, ...options });
  
  if (!response.ok) {
    throw new Error(`Erro na requisição: ${response.status}`);
  }
  
  return response.json();
}

// Funções de autenticação
async function login() {
  const username = document.getElementById('loginUser').value;
  const password = document.getElementById('loginPass').value;
  
  if (!username || !password) {
    alert('Preencha usuário e senha.');
    return;
  }
  
  try {
    const data = await apiRequest('login.php', {
      method: 'POST',
      body: JSON.stringify({ username, password })
    });
    
    currentUser = data.user;
    sessionStorage.setItem('rc_user', JSON.stringify(currentUser));
    route('dashboard');
    refreshDashboard();
  } catch (error) {
    alert('Usuário ou senha inválidos.');
    console.error('Erro no login:', error);
  }
}

function logout() {
  currentUser = null;
  sessionStorage.removeItem('rc_user');
  document.getElementById('loginPass').value = '';
  route('login');
  updateHeader();
}

// Roteamento
function route(view) {
  document.getElementById('viewLogin').classList.add('hidden');
  document.getElementById('viewDashboard').classList.add('hidden');
  document.getElementById('viewForm').classList.add('hidden');
  document.getElementById('viewDetail').classList.add('hidden');
  
  if (view === 'login') document.getElementById('viewLogin').classList.remove('hidden');
  if (view === 'dashboard') document.getElementById('viewDashboard').classList.remove('hidden');
  if (view === 'form') document.getElementById('viewForm').classList.remove('hidden');
  if (view === 'detail') document.getElementById('viewDetail').classList.remove('hidden');
  
  updateHeader();
}

// Atualizar cabeçalho
function updateHeader() {
  const span = document.getElementById('currentUserInfo');
  const btn = document.getElementById('btnLogout');
  
  if (currentUser) {
    span.textContent = `${currentUser.nome} • ${currentUser.nivel_liberacao}`;
    btn.classList.remove('hidden');
  } else {
    span.textContent = 'Não autenticado';
    btn.classList.add('hidden');
  }
}

// Funções para gerenciar requisições
async function loadRequisicoes(filters = {}) {
  try {
    const queryParams = new URLSearchParams(filters).toString();
    const data = await apiRequest(`requisicoes.php?${queryParams}`);
    return data;
  } catch (error) {
    console.error('Erro ao carregar requisições:', error);
    return [];
  }
}

async function saveRequisicao(requisicaoData, isSubmit = false) {
  try {
    console.log('Dados a serem enviados:', requisicaoData);
    
    const endpoint = editingReqId ? `requisicoes.php?id=${editingReqId}` : 'requisicoes.php';
    const method = editingReqId ? 'PUT' : 'POST';
    
    const data = await apiRequest(endpoint, {
      method,
      body: JSON.stringify({
        ...requisicaoData,
        status: isSubmit ? 'Pendente' : 'Rascunho'
      })
    });
    
    alert(editingReqId ? 'Requisição atualizada.' : 'Requisição salva.');
    route('dashboard');
    refreshDashboard();
    
    return data;
  } catch (error) {
    console.error('Erro ao salvar requisição:', error.message);
    alert('Erro ao salvar requisição. Verifique o console para detalhes.');
  }
}

// Dashboard
async function refreshDashboard() {
  if (!currentUser) return;
  
  document.getElementById('roleLabel').textContent = currentUser.nivel_liberacao;
  
  try {
    const filters = {};
    const searchValue = document.getElementById('searchBox').value;
    const statusValue = document.getElementById('filterStatus').value;
    
    if (searchValue) filters.search = searchValue;
    if (statusValue) filters.status = statusValue;
    if (currentUser.nivel_liberacao !== 'ADMIN') filters.user_id = currentUser.id;
    
    const requisicoes = await loadRequisicoes(filters);
    renderCards(requisicoes);
    
    // Contar pendências
    let pending = 0;
    if (currentUser.nivel_liberacao === 'APROVADOR') {
      pending = requisicoes.filter(r => r.status === 'Pendente' && r.aprovador_id === currentUser.id).length;
    } else if (currentUser.nivel_liberacao === 'COMPRAS') {
      pending = requisicoes.filter(r => ['Aprovada', 'Em cotação'].includes(r.status)).length;
    }
    
    document.getElementById('myCounts').textContent = pending;
  } catch (error) {
    console.error('Erro ao atualizar dashboard:', error);
  }
}

function renderCards(requisicoes) {
  const wrap = document.getElementById('cards');
  wrap.innerHTML = '';
  
  if (requisicoes.length === 0) {
    wrap.innerHTML = '<div class="small">Nenhuma requisição encontrada.</div>';
    return;
  }
  
  requisicoes.forEach(r => {
    const card = document.createElement('div');
    card.className = 'card';
    
    const total = (r.itens || []).reduce((s, it) => s + (it.quantidade * it.preco_unitario), 0);
    const statusClass = 'badge status-' + r.status.replaceAll(' ', '\\ ');
    
    card.innerHTML = `
      <div class="flex" style="justify-content:space-between; align-items:flex-start;">
        <div>
          <h3>${r.codigo} • ${r.titulo}</h3>
          <div class="small">Solicitante: ${r.solicitante_nome || '—'} • ${fmtDate(r.criada_em)}</div>
        </div>
        <span class="${statusClass}">${r.status}</span>
      </div>
      <div class="small" style="margin-top:6px;">${r.descricao?.slice(0,140) || ''}</div>
      <div class="flex" style="justify-content:space-between; margin-top:8px;">
        <div class="small">CC: ${r.centro_custo || '—'} • Aprovador: ${r.aprovador_nome || '—'} • Compras: ${r.comprador_nome || '—'}</div>
        <div><strong>Total:</strong> ${money(total)}</div>
      </div>
      <div class="right" style="margin-top:10px;">
        <button class="btn" data-open="${r.id}">Abrir</button>
      </div>
    `;
    
    wrap.appendChild(card);
  });
  
  // Bind open buttons
  wrap.querySelectorAll('button[data-open]').forEach(b => {
    b.addEventListener('click', e => openDetail(parseInt(e.target.getAttribute('data-open'))));
  });
}

// Formulário de requisição
function newReq() {
  editingReqId = null;
  document.getElementById('formMode').textContent = '(nova)';
  ['reqTitle', 'reqDesc', 'reqCC', 'reqNeedDate', 'reqVendor'].forEach(id => {
    document.getElementById(id).value = '';
  });
  
  document.getElementById('itemsTable').querySelector('tbody').innerHTML = '';
  document.getElementById('filesList').innerHTML = '';
  updateItemTotal();
  updateGrandTotal();
  populateApprovers();
  route('form');
}

async function populateApprovers() {
  try {
    const data = await apiRequest('usuarios.php?nivel=APROVADOR');
    const sel = document.getElementById('reqApprover');
    sel.innerHTML = '';
    
    data.forEach(u => {
      const opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = u.nome;
      sel.appendChild(opt);
    });
  } catch (error) {
    console.error('Erro ao carregar aprovadores:', error);
  }
}

function addItem() {
  const d = document.getElementById('itemDesc').value.trim();
  const q = parseFloat(document.getElementById('itemQty').value || '0');
  const p = parseFloat(document.getElementById('itemPrice').value || '0');
  const u = document.getElementById('itemUnit').value;
  
  if (!d || q <= 0) {
    alert('Preencha descrição e quantidade.');
    return;
  }
  
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${d}</td>
    <td class="nowrap">${q}</td>
    <td class="nowrap">${u}</td>
    <td class="nowrap">${p.toFixed(2)}</td>
    <td class="nowrap">${(q * p).toFixed(2)}</td>
    <td><button class="btn danger" data-del>Remover</button></td>
  `;
  
  document.querySelector('#itemsTable tbody').appendChild(tr);
  
  document.getElementById('itemDesc').value = '';
  document.getElementById('itemQty').value = '1';
  document.getElementById('itemPrice').value = '0';
  
  updateGrandTotal();
  
  tr.querySelector('[data-del]').addEventListener('click', () => {
    tr.remove();
    updateGrandTotal();
  });
}

function updateItemTotal() {
  const q = parseFloat(document.getElementById('itemQty').value || '0');
  const p = parseFloat(document.getElementById('itemPrice').value || '0');
  document.getElementById('itemTotal').textContent = (q * p).toFixed(2);
}

function updateGrandTotal() {
  const rows = Array.from(document.querySelectorAll('#itemsTable tbody tr'));
  const total = rows.reduce((s, tr) => {
    return s + parseFloat(tr.children[4].textContent.replace(',', '.'));
  }, 0);
  
  document.getElementById('grandTotal').textContent = money(total);
}

function addFile() {
  const fileInput = document.getElementById('fileUpload');
  const file = fileInput.files[0];
  
  if (!file) {
    alert('Selecione um arquivo.');
    return;
  }
  
  const span = document.createElement('span');
  span.className = 'chip';
  span.textContent = file.name;
  
  const btn = document.createElement('button');
  btn.className = 'btn ghost';
  btn.textContent = 'x';
  btn.style.padding = '2px 6px';
  btn.addEventListener('click', () => span.remove());
  
  span.appendChild(btn);
  document.getElementById('filesList').appendChild(span);
  fileInput.value = '';
}

function collectItems() {
  return Array.from(document.querySelectorAll('#itemsTable tbody tr')).map(tr => ({
    descricao: tr.children[0].textContent,
    quantidade: parseFloat(tr.children[1].textContent),
    unidade_medida: tr.children[2].textContent,
    preco_unitario: parseFloat(tr.children[3].textContent)
  }));
}

function collectFiles() {
  return Array.from(document.querySelectorAll('#filesList .chip')).map(ch => ch.childNodes[0].nodeValue.trim());
}

async function saveReq(submit = false) {
  if (!currentUser) {
    alert('Faça login.');
    return;
  }
  
  const title = document.getElementById('reqTitle').value.trim();
  const desc = document.getElementById('reqDesc').value.trim();
  const cc = document.getElementById('reqCC').value.trim();
  const needDate = document.getElementById('reqNeedDate').value || null;
  const vendor = document.getElementById('reqVendor').value.trim();
  const approverId = parseInt(document.getElementById('reqApprover').value);
  const items = collectItems();
  const files = collectFiles();
  
  if (!title || items.length === 0 || !approverId) {
    alert('Informe um título, ao menos um item e selecione um aprovador.');
    return;
  }
  
  const requisicaoData = {
    titulo: title,
    descricao: desc,
    centro_custo: cc,
    data_necessidade: needDate,
    fornecedor_sugerido: vendor,
    aprovador_id: approverId,
    solicitante_id: currentUser.id, // Adicionar o ID do usuário logado
    itens: items,
    anexos: files,
    user_id: currentUser.id // Para o histórico
  };
  
  await saveRequisicao(requisicaoData, submit);
}

async function openDetail(id) {
  try {
    console.log('Buscando requisição com ID:', id);
    
    const data = await apiRequest(`requisicoes.php?id=${id}`);
    console.log('Resposta da API:', data);
    
    if (!data || data.message === "Requisição não encontrada") {
      alert('Requisição não encontrada. ID: ' + id);
      return;
    }
    
    const r = data;
    
    // Header/status
    const statusSpan = document.getElementById('detailStatus');
    statusSpan.textContent = r.status;
    statusSpan.className = 'badge status-' + r.status.replaceAll(' ', '\\ ');
    
    // Converter preços para número (caso venham como string do banco)
    if (r.itens && Array.isArray(r.itens)) {
      r.itens = r.itens.map(item => ({
        ...item,
        quantidade: parseFloat(item.quantidade) || 0,
        preco_unitario: parseFloat(item.preco_unitario) || 0
      }));
    }
    
    // Body
    const total = r.itens.reduce((s, it) => s + (it.quantidade * it.preco_unitario), 0);
    
    const body = `
      <div class="grid cols-2">
        <div class="panel" style="padding:12px; background:transparent; border:1px dashed var(--border);">
          <h3 style="margin:0 0 8px;">${r.codigo} • ${r.titulo}</h3>
          <div class="small">Criada em ${fmtDate(r.criada_em)} por ${r.solicitante_nome}</div>
          <div class="sep"></div>
          <div class="row">
            <div><label>Centro de Custo</label><div>${r.centro_custo || '—'}</div></div>
            <div><label>Data Necessidade</label><div>${fmtDate(r.data_necessidade)}</div></div>
          </div>
          <div class="sep"></div>
          <div><label>Descrição</label><div>${r.descricao || '—'}</div></div>
          <div class="sep"></div>
          <div class="row">
            <div><label>Aprovador</label><div>${r.aprovador_nome || '—'}</div></div>
            <div><label>Compras</label><div>${r.comprador_nome || '—'}</div></div>
          </div>
          <div class="sep"></div>
          <div><label>Fornecedor sugerido</label><div>${r.fornecedor_sugerido || '—'}</div></div>
        </div>
        <div class="panel" style="padding:12px; background:transparent; border:1px dashed var(--border);">
          <h3 style="margin:0 0 8px;">Itens</h3>
          <table class="table">
            <thead><tr><th>Descrição</th><th>Qtd</th><th>Unidade</th><th>Unit. (R$)</th><th>Total</th></tr></thead>
            <tbody>
              ${r.itens.map(it => `
                <tr>
                  <td>${it.descricao}</td>
                  <td>${it.quantidade}</td>
                  <td>${it.unidade_medida || 'UN'}</td>
                  <td>${parseFloat(it.preco_unitario).toFixed(2)}</td>
                  <td>${(parseFloat(it.quantidade) * parseFloat(it.preco_unitario)).toFixed(2)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="right" style="margin-top:8px;"><strong>Total:</strong> ${money(total)}</div>
        </div>
      </div>
    `;
    
    document.getElementById('detailBody').innerHTML = body;
    
    // Ações conforme papel/status
    const area = document.getElementById('actionsArea');
    area.innerHTML = '';
    
    const isMyApproval = currentUser?.nivel_liberacao === 'APROVADOR' && r.aprovador_id == currentUser.id && r.status === 'Pendente';
    const canEdit = currentUser?.id == r.solicitante_id && (r.status === 'Rascunho' || r.status === 'Rejeitada');
    const isBuyer = currentUser?.nivel_liberacao === 'COMPRAS' && ['Aprovada', 'Em cotação', 'Pedido Emitido', 'Em Entrega'].includes(r.status);
    
    if (isMyApproval) {
      area.innerHTML = `
        <textarea id="decisionComment" rows="3" placeholder="Comentário (opcional)" style="flex:1"></textarea>
        <button id="btnApprove" class="btn success">Aprovar</button>
        <button id="btnReject" class="btn danger">Rejeitar</button>
      `;
      
      document.getElementById('btnApprove').onclick = () => decide(r.id, true);
      document.getElementById('btnReject').onclick = () => decide(r.id, false);
    }
    
    if (canEdit) {
      const b = document.createElement('button');
      b.className = 'btn';
      b.textContent = 'Editar';
      b.onclick = () => editReq(r.id);
      area.appendChild(b);
    }
    
    if (isBuyer) {
      area.innerHTML += `
        <input id="trkSupplier" placeholder="Fornecedor" style="max-width:200px;" />
        <input id="trkPO" placeholder="Nº Pedido" style="max-width:120px;" />
        <input id="trkETA" type="date" style="max-width:160px;" />
        <select id="trkStatus">
          <option ${r.status === 'Em cotação' ? 'selected' : ''}>Em cotação</option>
          <option ${r.status === 'Pedido Emitido' ? 'selected' : ''}>Pedido Emitido</option>
          <option ${r.status === 'Em Entrega' ? 'selected' : ''}>Em Entrega</option>
          <option ${r.status === 'Concluída' ? 'selected' : ''}>Concluída</option>
          <option ${r.status === 'Cancelada' ? 'selected' : ''}>Cancelada</option>
        </select>
        <button id="btnTrack" class="btn primary">Atualizar acompanhamento</button>
      `;
      
      document.getElementById('btnTrack').onclick = () => track(r.id);
    }
    
    // Renderizar histórico se existir
    if (r.historico && Array.isArray(r.historico)) {
      renderTimeline(r.historico);
    } else {
      document.getElementById('timeline').innerHTML = '<div class="small">Nenhum histórico disponível.</div>';
    }
    
    route('detail');
  } catch (error) {
    console.error('Erro ao carregar detalhes:', error);
    alert('Erro ao carregar detalhes da requisição. Verifique o console.');
  }
}

function renderTimeline(historico) {
  const wrap = document.getElementById('timeline');
  wrap.innerHTML = '';
  
  historico.forEach(ev => {
    const p = document.createElement('div');
    p.className = 'panel';
    p.style.padding = '10px';
    p.innerHTML = `
      <div class='small'>${new Date(ev.data_acao).toLocaleString('pt-BR')}</div>
      <div><strong>${ev.acao}</strong></div>
      <div class='small'>${ev.descricao || ''}</div>
    `;
    wrap.appendChild(p);
  });
}

async function decide(id, ok) {
  try {
    const comment = document.getElementById('decisionComment')?.value || '';
    
    await apiRequest(`requisicoes_decisao.php?id=${id}`, {
      method: 'POST',
      body: JSON.stringify({
        decisao: ok ? 'APROVADA' : 'REJEITADA',
        comentario: comment,
        usuario_id: currentUser.id
      })
    });
    
    openDetail(id);
    refreshDashboard();
  } catch (error) {
    console.error('Erro ao registrar decisão:', error);
    alert('Erro ao registrar decisão.');
  }
}

async function track(id) {
  try {
    const supplier = document.getElementById('trkSupplier').value.trim();
    const po = document.getElementById('trkPO').value.trim();
    const eta = document.getElementById('trkETA').value || null;
    const status = document.getElementById('trkStatus').value;
    
    await apiRequest(`requisicoes.php?id=${id}/acompanhamento`, {
      method: 'POST',
      body: JSON.stringify({
        status,
        fornecedor: supplier,
        numero_pedido: po,
        data_entrega_estimada: eta
      })
    });
    
    openDetail(id);
    refreshDashboard();
  } catch (error) {
    console.error('Erro ao atualizar acompanhamento:', error);
    alert('Erro ao atualizar acompanhamento.');
  }
}

async function editReq(id) {
  try {
    const data = await apiRequest(`requisicoes.php?id=${id}`);
    const r = data;
    
    if (!r) return;
    
    // Converter preços para número (caso venham como string do banco)
    if (r.itens && Array.isArray(r.itens)) {
      r.itens = r.itens.map(item => ({
        ...item,
        quantidade: parseFloat(item.quantidade) || 0,
        preco_unitario: parseFloat(item.preco_unitario) || 0
      }));
    }
    
    editingReqId = id;
    document.getElementById('formMode').textContent = `(${r.codigo})`;
    document.getElementById('reqTitle').value = r.titulo || '';
    document.getElementById('reqDesc').value = r.descricao || '';
    document.getElementById('reqCC').value = r.centro_custo || '';
    document.getElementById('reqNeedDate').value = r.data_necessidade || '';
    document.getElementById('reqVendor').value = r.fornecedor_sugerido || '';
    
    populateApprovers();
    document.getElementById('reqApprover').value = r.aprovador_id || '';
    
    const tbody = document.querySelector('#itemsTable tbody');
    tbody.innerHTML = '';
    
    (r.itens || []).forEach(it => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.descricao}</td>
        <td class='nowrap'>${it.quantidade}</td>
        <td class='nowrap'>${it.unidade_medida || 'UN'}</td>
        <td class='nowrap'>${parseFloat(it.preco_unitario).toFixed(2)}</td>
        <td class='nowrap'>${(parseFloat(it.quantidade) * parseFloat(it.preco_unitario)).toFixed(2)}</td>
        <td><button class='btn danger' data-del>Remover</button></td>
      `;
      
      tbody.appendChild(tr);
      tr.querySelector('[data-del]').addEventListener('click', () => {
        tr.remove();
        updateGrandTotal();
      });
    });
    
    const fl = document.getElementById('filesList');
    fl.innerHTML = '';
    
    // Nota: Os anexos precisariam ser implementados conforme sua estrutura
    (r.anexos || []).forEach(f => {
      const span = document.createElement('span');
      span.className = 'chip';
      span.textContent = f.nome_arquivo || f;
      
      const btn = document.createElement('button');
      btn.className = 'btn ghost';
      btn.textContent = 'x';
      btn.style.padding = '2px 6px';
      btn.onclick = () => span.remove();
      
      span.appendChild(btn);
      fl.appendChild(span);
    });
    
    updateGrandTotal();
    route('form');
  } catch (error) {
    console.error('Erro ao carregar requisição para edição:', error);
    alert('Erro ao carregar requisição para edição.');
  }
}

// Inicialização
function init() {
  // Restaurar sessão se existir
  const savedUser = sessionStorage.getItem('rc_user');
  if (savedUser) {
    currentUser = JSON.parse(savedUser);
    route('dashboard');
    refreshDashboard();
  } else {
    route('login');
  }
  
  updateHeader();
  
  // Event listeners
  document.getElementById('btnLogin').addEventListener('click', login);
  document.getElementById('btnLogout').addEventListener('click', logout);
  document.getElementById('btnNewReq').addEventListener('click', newReq);
  document.getElementById('btnBack1').addEventListener('click', () => {
    route('dashboard');
    refreshDashboard();
  });
  document.getElementById('btnBack2').addEventListener('click', () => {
    route('dashboard');
    refreshDashboard();
  });
  document.getElementById('btnAddItem').addEventListener('click', addItem);
  document.getElementById('itemQty').addEventListener('input', updateItemTotal);
  document.getElementById('itemPrice').addEventListener('input', updateItemTotal);
  document.getElementById('btnAddFile').addEventListener('click', addFile);
  document.getElementById('btnSaveReq').addEventListener('click', () => saveReq(false));
  document.getElementById('btnSubmitReq').addEventListener('click', () => saveReq(true));
  document.getElementById('searchBox').addEventListener('input', refreshDashboard);
  document.getElementById('filterStatus').addEventListener('change', refreshDashboard);
}

// Iniciar a aplicação
init();