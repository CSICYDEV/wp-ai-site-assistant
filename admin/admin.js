(function () {
  const chatLog = document.getElementById('wp-ai-chat-log');
  const promptEl = document.getElementById('wp-ai-prompt');
  const sendBtn = document.getElementById('wp-ai-send');
  const proposalsEl = document.getElementById('wp-ai-proposals');
  const refreshLogsBtn = document.getElementById('wp-ai-refresh-logs');
  const logList = document.getElementById('wp-ai-log-list');

  function appendMessage(role, text) {
    const div = document.createElement('div');
    div.className = 'wp-ai-message ' + role;
    div.textContent = text;
    chatLog.appendChild(div);
    chatLog.scrollTop = chatLog.scrollHeight;
  }

  async function api(path, method = 'GET', body = null) {
    const args = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': WPAISiteAssistant.nonce,
      },
    };

    if (body) {
      args.body = JSON.stringify(body);
    }

    const response = await fetch(WPAISiteAssistant.restUrl + path, args);
    const data = await response.json();

    if (!response.ok) {
      const message = (data && data.message) ? data.message : 'Request failed.';
      throw new Error(message);
    }

    return data;
  }

  function renderProposal(data) {
    proposalsEl.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'wp-ai-proposal';

    const title = document.createElement('h3');
    title.textContent = 'Proposed Action: ' + data.tool;
    card.appendChild(title);

    if (data.message) {
      const intro = document.createElement('p');
      intro.textContent = data.message;
      card.appendChild(intro);
    }

    const pre = document.createElement('pre');
    pre.textContent = JSON.stringify(data.args, null, 2);
    card.appendChild(pre);

    const buttonRow = document.createElement('div');
    buttonRow.className = 'wp-ai-actions';

    const executeButton = document.createElement('button');
    executeButton.className = 'button button-primary';
    executeButton.textContent = data.safe ? 'Approve & Execute' : 'Execute Carefully';
    executeButton.addEventListener('click', async function () {
      executeButton.disabled = true;
      try {
        const result = await api('/execute', 'POST', {
          tool: data.tool,
          args: data.args,
        });
        appendMessage('assistant', result.message || 'Action completed.');
        if (result.edit_url) {
          const link = document.createElement('a');
          link.href = result.edit_url;
          link.textContent = 'Open edit screen';
          link.target = '_blank';
          card.appendChild(link);
        }
        loadLogs();
      } catch (error) {
        appendMessage('assistant', 'Error: ' + error.message);
      } finally {
        executeButton.disabled = false;
      }
    });

    const rejectButton = document.createElement('button');
    rejectButton.className = 'button';
    rejectButton.textContent = 'Dismiss';
    rejectButton.addEventListener('click', function () {
      proposalsEl.innerHTML = '';
    });

    buttonRow.appendChild(executeButton);
    buttonRow.appendChild(rejectButton);
    card.appendChild(buttonRow);
    proposalsEl.appendChild(card);

    if (data.safe && WPAISiteAssistant.autoExecuteSafe) {
      executeButton.click();
    }
  }

  async function loadLogs() {
    try {
      const data = await api('/logs');
      logList.innerHTML = '';
      if (!data.logs || !data.logs.length) {
        logList.innerHTML = '<p>No log entries yet.</p>';
        return;
      }
      data.logs.forEach((entry) => {
        const div = document.createElement('div');
        div.className = 'wp-ai-log-entry';
        div.innerHTML = '<strong>' + entry.timestamp + '</strong> <span>' + entry.action + '</span><code>' + JSON.stringify(entry.data) + '</code>';
        logList.appendChild(div);
      });
    } catch (error) {
      appendMessage('assistant', 'Could not refresh logs: ' + error.message);
    }
  }

  if (sendBtn) {
    sendBtn.addEventListener('click', async function () {
      const prompt = promptEl.value.trim();
      if (!prompt) return;

      appendMessage('user', prompt);
      proposalsEl.innerHTML = '';
      promptEl.value = '';

      try {
        const data = await api('/chat', 'POST', { prompt });
        if (data.mode === 'proposal') {
          appendMessage('assistant', 'I prepared an action for review.');
          renderProposal(data);
        } else {
          appendMessage('assistant', data.message || 'No response received.');
        }
      } catch (error) {
        appendMessage('assistant', 'Error: ' + error.message);
      }
    });
  }

  if (refreshLogsBtn) {
    refreshLogsBtn.addEventListener('click', loadLogs);
  }
})();
