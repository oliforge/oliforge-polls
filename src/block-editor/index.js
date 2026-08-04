(function (blocks, element, components, blockEditor, serverSideRender, apiFetch) {
  var el = element.createElement;
  var useState = element.useState;
  var useEffect = element.useEffect;
  var InspectorControls = blockEditor.InspectorControls;
  var useBlockProps = blockEditor.useBlockProps;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var Placeholder = components.Placeholder;
  var ServerSideRender = serverSideRender;

  blocks.registerBlockType('oliforge-polls/vote-embed', {
    edit: function (props) {
      var _a = useState([]), options = _a[0], setOptions = _a[1];

      useEffect(function () {
        if (window.OliForgePollsBlock && window.OliForgePollsBlock.debug) {
          console.log('OliForge Polls: Initializing fetch...', window.OliForgePollsBlock);
        }
        var restPath = (window.OliForgePollsBlock && window.OliForgePollsBlock.restPath) ? window.OliForgePollsBlock.restPath : ('/wp/v2/' + 'oliforge-polls');
        var fetchPath = restPath + '?per_page=100&_fields=id,title&status=publish';

        if (window.OliForgePollsBlock && window.OliForgePollsBlock.debug) {
           console.log('OliForge Polls: Fetching from:', fetchPath);
        }

        apiFetch({ path: fetchPath }).then(function (posts) {
          if (window.OliForgePollsBlock && window.OliForgePollsBlock.debug) {
            console.log('OliForge Polls: Posts fetched successfully', posts);
          }
          var opts = [{ label: 'Select poll', value: 0 }];
          if (Array.isArray(posts) && posts.length > 0) {
            opts = opts.concat(posts.map(function (post) {
              var title = (post.title && post.title.rendered) ? post.title.rendered : ('Poll #' + post.id);
              if (!title || title.trim() === '') {
                title = 'Poll #' + post.id;
              }
              return { label: title, value: post.id };
            }));
          } else if (Array.isArray(posts)) {
            opts = [{ label: 'No polls found. Create one first!', value: 0 }];
          } else {
             console.error('OliForge Polls: Unexpected response:', posts);
             opts = [{ label: 'Unexpected response format', value: 0 }];
          }
          setOptions(opts);
        }).catch(function(err) {
          console.error('OliForge Polls: Fetch error details:', err);
          var errorMsg = 'Error loading polls';
          if (err.message) {
            errorMsg += ': ' + err.message;
          } else if (typeof err === 'string') {
            errorMsg += ': ' + err;
          } else if (err.code) {
            errorMsg += ' (' + err.code + ')';
          }
          setOptions([{ label: errorMsg, value: 0 }]);
        });
      }, []);

      return el(element.Fragment, null,
        el(InspectorControls, null,
          el(PanelBody, { title: 'Poll settings', initialOpen: true },
            el(SelectControl, {
              label: 'Choose poll',
              value: props.attributes.pollId || 0,
              options: options,
              onChange: function (value) {
                props.setAttributes({ pollId: parseInt(value, 10) || 0 });
              }
            })
          )
        ),
        el('div', useBlockProps(),
          props.attributes.pollId ?
            el(ServerSideRender, { block: 'oliforge-polls/vote-embed', attributes: props.attributes }) :
            el(Placeholder, { label: 'OliForge Polls' }, 'Select a poll in the block settings.')
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.apiFetch);
