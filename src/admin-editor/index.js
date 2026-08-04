import './style.css';
(function (wp, config) {
  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var Fragment = wp.element.Fragment;
  var components = wp.components;
  var TextControl = components.TextControl;
  var TextareaControl = components.TextareaControl;
  var createPortal = wp.element.createPortal;
  var Button = components.Button;
  var ToggleControl = components.ToggleControl;
  var __experimentalNumberControl = components.__experimentalNumberControl || TextControl;
  var SelectControl = components.SelectControl;

  function uid() {
    return 'ans_' + Math.random().toString(36).slice(2, 10);
  }

  function setFieldSync(data) {
    var field = document.getElementById('oliforge-polls-data-field');
    if (field) {
      field.value = JSON.stringify(data);
    }
  }

  function number(value, fallback) {
    var n = parseInt(value, 10);
    return isNaN(n) ? fallback : n;
  }

  function App() {
    var initial = config && config.data ? config.data : {};
    var defaults = config && config.defaults ? config.defaults : {};
    var i18n = config && config.labels ? config.labels : {};
    var initialStyles = Object.assign({}, defaults.styles || {}, initial.styles || {});
    var initialLabels = Object.assign({}, defaults.labels || {}, initial.labels || {});
    var initialData = Object.assign({}, defaults, initial, { styles: initialStyles, labels: initialLabels });
    var _a = useState(initialData), data = _a[0], setData = _a[1];
    var _tab = useState('content'), activeTab = _tab[0], setActiveTab = _tab[1];

    useEffect(function () {
      setFieldSync(data);
    }, [data]);

    function update(path, value) {
      if (path.indexOf('styles.') === 0) {
        var key = path.replace('styles.', '');
        setData(Object.assign({}, data, {
          styles: Object.assign({}, data.styles, (_b = {}, _b[key] = value, _b))
        }));
      } else if (path.indexOf('labels.') === 0) {
        var key = path.replace('labels.', '');
        setData(Object.assign({}, data, {
          labels: Object.assign({}, data.labels, (_c = {}, _c[key] = value, _c))
        }));
      } else {
        var patch = {};
        patch[path] = value;
        setData(Object.assign({}, data, patch));
      }
      var _b, _c;
    }

    function updateAnswer(index, key, value) {
      var answers = (data.answers || []).slice();
      answers[index] = Object.assign({}, answers[index], (_a = {}, _a[key] = value, _a));
      setData(Object.assign({}, data, { answers: answers }));
      var _a;
    }

    function addAnswer() {
      setData(Object.assign({}, data, {
        answers: (data.answers || []).concat([{ id: uid(), text: '', votes: 0 }])
      }));
    }

    function removeAnswer(index) {
      var answers = (data.answers || []).slice();
      if (answers.length <= 2) return;
      answers.splice(index, 1);
      setData(Object.assign({}, data, { answers: answers }));
    }

    var preview = {
      background: data.styles.containerBg,
      border: data.styles.containerBorderWidth + 'px ' + data.styles.containerBorderStyle + ' ' + data.styles.containerBorderColor,
      borderRadius: data.styles.containerRadius + 'px',
      padding: data.styles.containerPadding + 'px',
      boxShadow: data.styles.containerShadow
    };

    var totalVotes = (data.answers || []).reduce(function (sum, ans) { return sum + (ans.votes || 0); }, 0);

    var isPast = false;
    var isBeforeStart = false;
    if (data.start_date) {
      var start = new Date(data.start_date);
      var nowStart = new Date();
      if (!isNaN(start.getTime())) {
        isBeforeStart = start > nowStart;
      }
    }
    if (data.end_date) {
      var end = new Date(data.end_date);
      var now = new Date();
      if (!isNaN(end.getTime())) {
        isPast = end < now;
      }
    }

    return el(Fragment, null,
      el('div', { className: 'alvl-builder' },
        el('div', { className: 'alvl-tabs', role: 'tablist', 'aria-label': 'Voter Builder sections' },
          [
            { name: 'content', title: i18n.content },
            { name: 'answers', title: i18n.answers },
            { name: 'labels', title: i18n.labels },
            { name: 'container', title: i18n.container },
            { name: 'question', title: i18n.qAndD },
            { name: 'answer-style', title: i18n.answersStyle },
            { name: 'button-results', title: i18n.buttonAndRes }
          ].map(function (tab) {
            return el(Button, {
              key: tab.name,
              className: 'alvl-tab-button' + (activeTab === tab.name ? ' is-active' : ''),
              onClick: function () { setActiveTab(tab.name); },
              'aria-selected': activeTab === tab.name,
              role: 'tab'
            }, tab.title);
          })
        ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'content' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.content },
        el(TextControl, {
          label: 'Question',
          value: data.question || '',
          onChange: function (value) { return update('question', value); }
        }),
        el(TextareaControl, {
          label: 'Description',
          value: data.description || '',
          onChange: function (value) { return update('description', value); }
        }),
        el(SelectControl, {
          label: 'Results visibility',
          value: data.results_visibility || 'after_vote',
          options: [
            { label: 'After voting', value: 'after_vote' },
            { label: 'Only after end date', value: 'after_end' },
            { label: 'Always show', value: 'always' },
            { label: 'Hidden', value: 'hidden' }
          ],
          onChange: function (value) {
            update('results_visibility', value);
            update('show_results_after_vote', value === 'after_vote');
          },
          help: 'Controls when percentages and vote counts are visible on the frontend.'
        }),
        el(TextControl, {
          label: 'Voting start date',
          type: 'datetime-local',
          value: data.start_date || '',
          onChange: function (value) { return update('start_date', value); },
          help: 'Before this date, voting is not yet open.'
        }),
        el(TextControl, {
          label: 'Voting end date',
          type: 'datetime-local',
          value: data.end_date || '',
          onChange: function (value) { return update('end_date', value); },
          help: 'After this date, voting is closed and final results are shown.'
        })
      ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'answers' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.answers },
        (data.answers || []).map(function (answer, index) {
          return el('div', { className: 'alvl-answer-row', key: answer.id || index },
            el(TextControl, {
              label: 'Answer ' + (index + 1),
              value: answer.text || '',
              onChange: function (value) { return updateAnswer(index, 'text', value); }
            }),
            el(__experimentalNumberControl, {
              label: 'Votes',
              value: answer.votes,
              onChange: function (value) { return updateAnswer(index, 'votes', number(value, 0)); }
            }),
            el(Button, {
              isDestructive: true,
              onClick: function () { return removeAnswer(index); },
              disabled: (data.answers || []).length <= 2
            }, i18n.remove)
          );
        }),
        el('div', { className: 'alvl-toolbar' },
          el(Button, { variant: 'secondary', onClick: addAnswer }, i18n.addAnswer)
        )
      ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'labels' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.labels },
        el(TextControl, {
          label: 'Vote button text',
          value: data.labels.voteButton,
          onChange: function (value) { return update('labels.voteButton', value); }
        }),
        el(TextControl, {
          label: 'Total votes text',
          value: data.labels.totalVotes,
          onChange: function (value) { return update('labels.totalVotes', value); },
          help: 'Use %d for the number of votes.'
        }),
        el(TextControl, {
          label: 'Voting starts text',
          value: data.labels.votingStarts,
          onChange: function (value) { return update('labels.votingStarts', value); },
          help: 'Use %s for the date.'
        }),
        el(TextControl, {
          label: 'Voting ends text',
          value: data.labels.votingEnds,
          onChange: function (value) { return update('labels.votingEnds', value); },
          help: 'Use %s for the date.'
        }),
        el(TextControl, {
          label: 'Voted success message',
          value: data.labels.votedMessage,
          onChange: function (value) { return update('labels.votedMessage', value); }
        }),
        el(TextControl, {
          label: 'Already voted message',
          value: data.labels.alreadyVoted,
          onChange: function (value) { return update('labels.alreadyVoted', value); }
        }),
        el(TextControl, {
          label: 'Voting closed message',
          value: data.labels.closedMessage,
          onChange: function (value) { return update('labels.closedMessage', value); }
        }),
        el(TextControl, {
          label: 'Voting not started message',
          value: data.labels.notStartedMessage,
          onChange: function (value) { return update('labels.notStartedMessage', value); }
        }),
        el('div', { className: 'alvl-toolbar' },
          el(Button, {
            variant: 'secondary',
            onClick: function () {
              if (confirm(i18n.confirmReset)) {
                setData(Object.assign({}, data, { labels: Object.assign({}, defaults.labels) }));
              }
            }
          }, i18n.setDefaults)
        )
      ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'container' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.container },
        el('div', { className: 'alvl-grid alvl-grid-3' },
          el(TextControl, { label: 'Background', type: 'color', value: data.styles.containerBg, onChange: function (v) { return update('styles.containerBg', v); } }),
          el(TextControl, { label: 'Border color', type: 'color', value: data.styles.containerBorderColor, onChange: function (v) { return update('styles.containerBorderColor', v); } }),
          el(__experimentalNumberControl, { label: 'Border width', value: data.styles.containerBorderWidth, onChange: function (v) { return update('styles.containerBorderWidth', number(v, 1)); } }),
          el(SelectControl, { label: 'Border style', value: data.styles.containerBorderStyle, options: [{ label: 'solid', value: 'solid' }, { label: 'dashed', value: 'dashed' }, { label: 'dotted', value: 'dotted' }, { label: 'none', value: 'none' }], onChange: function (v) { return update('styles.containerBorderStyle', v); } }),
          el(__experimentalNumberControl, { label: 'Radius', value: data.styles.containerRadius, onChange: function (v) { return update('styles.containerRadius', number(v, 12)); } }),
          el(__experimentalNumberControl, { label: 'Padding', value: data.styles.containerPadding, onChange: function (v) { return update('styles.containerPadding', number(v, 20)); } }),
          el(TextControl, { label: 'Shadow', value: data.styles.containerShadow, onChange: function (v) { return update('styles.containerShadow', v); } })
        )
      ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'question' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.qAndD },
        el('div', { className: 'alvl-grid alvl-grid-3' },
          el(TextControl, { label: 'Question font family', value: data.styles.questionFontFamily, onChange: function (v) { return update('styles.questionFontFamily', v); } }),
          el(__experimentalNumberControl, { label: 'Question font size', value: data.styles.questionFontSize, onChange: function (v) { return update('styles.questionFontSize', number(v, 24)); } }),
          el(__experimentalNumberControl, { label: 'Question weight', value: data.styles.questionFontWeight, onChange: function (v) { return update('styles.questionFontWeight', number(v, 700)); } }),
          el(TextControl, { label: 'Question color', type: 'color', value: data.styles.questionColor, onChange: function (v) { return update('styles.questionColor', v); } }),
          el(__experimentalNumberControl, { label: 'Description font size', value: data.styles.descriptionFontSize, onChange: function (v) { return update('styles.descriptionFontSize', number(v, 14)); } }),
          el(TextControl, { label: 'Description color', type: 'color', value: data.styles.descriptionColor, onChange: function (v) { return update('styles.descriptionColor', v); } })
        )
      ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'answer-style' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.answersStyle },
        el('div', { className: 'alvl-grid alvl-grid-3' },
          el(TextControl, { label: 'Answer font family', value: data.styles.answerFontFamily, onChange: function (v) { return update('styles.answerFontFamily', v); } }),
          el(__experimentalNumberControl, { label: 'Answer font size', value: data.styles.answerFontSize, onChange: function (v) { return update('styles.answerFontSize', number(v, 16)); } }),
          el(TextControl, { label: 'Answer text color', type: 'color', value: data.styles.answerColor, onChange: function (v) { return update('styles.answerColor', v); } }),
          el(TextControl, { label: 'Answer background', type: 'color', value: data.styles.answerBg, onChange: function (v) { return update('styles.answerBg', v); } }),
          el(TextControl, { label: 'Answer border color', type: 'color', value: data.styles.answerBorderColor, onChange: function (v) { return update('styles.answerBorderColor', v); } }),
          el(__experimentalNumberControl, { label: 'Answer border width', value: data.styles.answerBorderWidth, onChange: function (v) { return update('styles.answerBorderWidth', number(v, 1)); } }),
          el(__experimentalNumberControl, { label: 'Answer radius', value: data.styles.answerBorderRadius, onChange: function (v) { return update('styles.answerBorderRadius', number(v, 10)); } })
        )
      ),
      el('div', { className: 'alvl-tab-section' + (activeTab === 'button-results' ? ' is-active' : ''), role: 'tabpanel', 'aria-label': i18n.buttonAndRes },
        el('div', { className: 'alvl-grid alvl-grid-3' },
          el(TextControl, { label: 'Button background', type: 'color', value: data.styles.buttonBg, onChange: function (v) { return update('styles.buttonBg', v); } }),
          el(TextControl, { label: 'Button text color', type: 'color', value: data.styles.buttonTextColor, onChange: function (v) { return update('styles.buttonTextColor', v); } }),
          el(__experimentalNumberControl, { label: 'Button font size', value: data.styles.buttonFontSize, onChange: function (v) { return update('styles.buttonFontSize', number(v, 15)); } }),
          el(__experimentalNumberControl, { label: 'Button radius', value: data.styles.buttonRadius, onChange: function (v) { return update('styles.buttonRadius', number(v, 10)); } }),
          el(__experimentalNumberControl, { label: 'Button padding Y', value: data.styles.buttonPaddingY, onChange: function (v) { return update('styles.buttonPaddingY', number(v, 12)); } }),
          el(__experimentalNumberControl, { label: 'Button padding X', value: data.styles.buttonPaddingX, onChange: function (v) { return update('styles.buttonPaddingX', number(v, 16)); } }),
          el(SelectControl, {
            label: 'Button position',
            value: data.styles.buttonPosition,
            options: [
              { label: 'Left', value: 'left' },
              { label: 'Center', value: 'center' },
              { label: 'Right', value: 'right' }
            ],
            onChange: function (v) { return update('styles.buttonPosition', v); }
          }),
          el(TextControl, { label: 'Results text color', type: 'color', value: data.styles.resultsTextColor, onChange: function (v) { return update('styles.resultsTextColor', v); } }),
          el(__experimentalNumberControl, { label: 'Results font size', value: data.styles.resultsFontSize, onChange: function (v) { return update('styles.resultsFontSize', number(v, 15)); } }),
          el(TextControl, { label: 'Progress background', type: 'color', value: data.styles.progressBg, onChange: function (v) { return update('styles.progressBg', v); } }),
          el(TextControl, { label: 'Progress fill', type: 'color', value: data.styles.progressFill, onChange: function (v) { return update('styles.progressFill', v); } }),
          el(__experimentalNumberControl, { label: 'Progress height', value: data.styles.progressHeight, onChange: function (v) { return update('styles.progressHeight', number(v, 10)); } }),
          el(TextControl, { label: 'Status color', type: 'color', value: data.styles.statusColor, onChange: function (v) { return update('styles.statusColor', v); } })
        )
      ),
      ),
      createPortal(el(Fragment, null,
        el('div', { className: 'alvl-preview' },
          el('h3', null, i18n.livePreview),
          el('div', { style: preview },
            el('div', { style: { fontSize: data.styles.questionFontSize + 'px', color: data.styles.questionColor, fontWeight: data.styles.questionFontWeight, fontFamily: data.styles.questionFontFamily, marginBottom: '10px' } }, data.question || i18n.yourQuestion),
            isBeforeStart ? el('div', { style: { color: data.styles.statusColor, marginBottom: '12px', fontWeight: 'bold' } }, data.labels.notStartedMessage) : null,
            isPast ? el('div', { style: { color: data.styles.statusColor, marginBottom: '12px', fontWeight: 'bold' } }, data.labels.closedMessage) : null,
            data.description ? el('div', { className: 'alvl-muted', style: { fontSize: data.styles.descriptionFontSize + 'px', color: data.styles.descriptionColor, marginBottom: '12px' } }, data.description) : null,
            data.start_date ? el('div', { style: { fontSize: data.styles.descriptionFontSize + 'px', color: data.styles.descriptionColor, marginBottom: '8px', fontStyle: 'italic' } }, data.labels.votingStarts.replace('%s', data.start_date)) : null,
            data.end_date ? el('div', { style: { fontSize: data.styles.descriptionFontSize + 'px', color: data.styles.descriptionColor, marginBottom: '12px', fontStyle: 'italic' } }, data.labels.votingEnds.replace('%s', data.end_date)) : null,
            el('div', { style: { marginTop: '12px', display: 'grid', gap: '8px' } },
              (data.answers || []).map(function (answer, index) {
                return el('div', {
                  key: answer.id || index,
                  style: {
                    padding: '12px 14px',
                    background: data.styles.answerBg,
                    color: data.styles.answerColor,
                    border: data.styles.answerBorderWidth + 'px solid ' + data.styles.answerBorderColor,
                    borderRadius: data.styles.answerBorderRadius + 'px',
                    fontSize: data.styles.answerFontSize + 'px',
                    fontFamily: data.styles.answerFontFamily
                  }
                }, answer.text || 'Answer');
              })
            ),
            el('div', { style: { marginTop: '20px', textAlign: data.styles.buttonPosition } },
              el(Button, {
                style: {
                  background: data.styles.buttonBg,
                  color: data.styles.buttonTextColor,
                  fontSize: data.styles.buttonFontSize + 'px',
                  borderRadius: data.styles.buttonRadius + 'px',
                  padding: data.styles.buttonPaddingY + 'px ' + data.styles.buttonPaddingX + 'px',
                  border: 'none',
                  cursor: 'default',
                  height: 'auto',
                  lineHeight: '1.2'
                }
              }, data.labels.voteButton)
            )
          )
        ),
        el('div', { className: 'alvl-preview alvl-preview-results' },
          el('h3', null, i18n.liveResults),
          el('div', { className: 'alvl-results-card' },
            el('div', { style: { fontSize: data.styles.questionFontSize + 'px', color: data.styles.questionColor, fontWeight: data.styles.questionFontWeight, fontFamily: data.styles.questionFontFamily, marginBottom: '14px' } }, data.question || i18n.yourQuestion),
            el('div', { style: { marginBottom: '12px', fontSize: data.styles.resultsFontSize + 'px', color: data.styles.resultsTextColor, fontWeight: 'bold' } }, data.labels.totalVotes.replace('%d', totalVotes)),
            el('div', null,
              (data.answers || []).map(function (answer, index) {
                var percent = totalVotes > 0 ? Math.round(((answer.votes || 0) / totalVotes) * 100 * 10) / 10 : 0;
                return el('div', { key: 'res_' + (answer.id || index), style: { marginBottom: '14px' } },
                  el('div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: '6px', fontSize: data.styles.resultsFontSize + 'px', color: data.styles.resultsTextColor } },
                    el('span', null, answer.text || 'Answer'),
                    el('span', null, (answer.votes || 0) + ' / ' + percent + '%')
                  ),
                  el('div', {
                    style: {
                      width: '100%',
                      background: data.styles.progressBg,
                      borderRadius: '999px',
                      overflow: 'hidden',
                      height: data.styles.progressHeight + 'px'
                    }
                  },
                    el('span', {
                      style: {
                        display: 'block',
                        height: '100%',
                        width: percent + '%',
                        background: data.styles.progressFill,
                        borderRadius: '999px'
                      }
                    })
                  )
                );
              })
            )
          )
        )
      ), document.getElementById('oliforge-polls-preview-root'))
    );
  }

  wp.element.render(el(App), document.getElementById('oliforge-polls-admin-root'));
})(window.wp, window.OliForgePollsAdmin || {});
