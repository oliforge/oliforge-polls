import './style.css';
(function () {
  function getI18n(key, fallback) {
    return window.OliForgePolls && OliForgePolls.i18n && OliForgePolls.i18n[key] ? OliForgePolls.i18n[key] : fallback;
  }

  function getErrorMessage(payload, fallback) {
    if (!payload) return fallback;
    if (payload.message) return payload.message;
    if (payload.data && payload.data.message) return payload.data.message;
    return fallback;
  }

  function updateResults(root, payload) {
    var formWrap = root.querySelector('.oliforge_polls__form-wrap');
    var resultsWrap = root.querySelector('.oliforge_polls__results');
    var questionNode = root.querySelector('.oliforge_polls__question');
    var totalNode = root.querySelector('.oliforge_polls__results-total');
    var statusNode = root.querySelector('.oliforge_polls__status');
    var itemNodes = root.querySelectorAll('.oliforge_polls__result-item');
    var labelsRaw = root.getAttribute('data-labels');
    var labels = {};

    try {
      if (labelsRaw) {
        labels = JSON.parse(labelsRaw);
      }
    } catch (e) {
      console.error('Failed to parse labels', e);
    }

    if (formWrap) {
      formWrap.style.display = 'none';
    }
    if (questionNode) {
      questionNode.style.display = 'none';
    }
    if (resultsWrap) {
      if (payload.showResults === false) {
        resultsWrap.classList.add('is-hidden');
      } else {
        resultsWrap.classList.remove('is-hidden');
      }
    }
    if (statusNode) {
      statusNode.textContent = payload.message || labels.votedMessage || getI18n('voted', 'Voted');
      statusNode.classList.remove('is-hidden');
    }
    if (totalNode) {
      var totalText = labels.totalVotes || 'Total votes: %d';
      totalNode.textContent = totalText.replace('%d', payload.total);
    }

    itemNodes.forEach(function (node) {
      var answerId = node.getAttribute('data-answer-id');
      var match = payload.results.find(function (item) {
        return item.id === answerId;
      });
      if (!match) return;
      var values = node.querySelector('.oliforge_polls__result-values');
      var fill = node.querySelector('.oliforge_polls__progress-fill');
      if (values) values.textContent = match.votes + ' / ' + match.percent + '%';
      if (fill) fill.style.width = match.percent + '%';
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('.oliforge_polls__form');
    if (!form) return;

    event.preventDefault();

    var root = form.closest('.oliforge-polls');
    var notice = form.querySelector('.oliforge_polls__notice');
    var checked = form.querySelector('input[type="radio"]:checked');
    var pollId = root ? root.getAttribute('data-poll-id') : '';

    if (!checked) {
      if (notice) notice.textContent = getI18n('chooseOption', 'Please choose an answer.');
      return;
    }

    if (!window.OliForgePolls || !OliForgePolls.restUrl || !OliForgePolls.nonce || !pollId) {
      if (notice) notice.textContent = getI18n('error', 'Could not save your vote. Please try again.');
      return;
    }

    fetch(OliForgePolls.restUrl.replace(/\/$/, '') + '/polls/' + encodeURIComponent(pollId) + '/vote', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': OliForgePolls.nonce
      },
      body: JSON.stringify({
        answer_index: parseInt(checked.value, 10)
      })
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) {
          if (!response.ok) {
            throw new Error(getErrorMessage(json, getI18n('error', 'Could not save your vote. Please try again.')));
          }
          return json;
        });
      })
      .then(function (json) {
        updateResults(root, json);
      })
      .catch(function (error) {
        if (notice) notice.textContent = error.message || getI18n('error', 'Could not save your vote. Please try again.');
      });
  });
})();
