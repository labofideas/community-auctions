/* global wp */
(function (blocks, element, i18n, components, blockEditor, serverSideRender) {
  var el = element.createElement;
  var __ = i18n.__;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var TextControl = components.TextControl;
  var SelectControl = components.SelectControl;
  var ToggleControl = components.ToggleControl;

  blocks.registerBlockType('community-auctions/auctions-list', {
    title: __('Community Auctions List', 'community-auctions'),
    description: __('Display a list of community auctions.', 'community-auctions'),
    icon: 'hammer',
    category: 'widgets',
    attributes: {
      perPage: { type: 'number', default: 10 },
      status: { type: 'string', default: 'live' },
      ending: { type: 'string', default: '' },
      minBid: { type: 'string', default: '' },
      showGroupBadge: { type: 'boolean', default: true }
    },
    edit: function (props) {
      var attrs = props.attributes;
      return [
        el(
          InspectorControls,
          { key: 'controls' },
          el(
            PanelBody,
            { title: __('Auction Settings', 'community-auctions'), initialOpen: true },
            el(TextControl, {
              label: __('Per page', 'community-auctions'),
              type: 'number',
              value: attrs.perPage,
              onChange: function (value) {
                props.setAttributes({ perPage: parseInt(value || '0', 10) || 0 });
              }
            }),
            el(SelectControl, {
              label: __('Status', 'community-auctions'),
              value: attrs.status,
              options: [
                { label: __('Live', 'community-auctions'), value: 'live' },
                { label: __('Ended', 'community-auctions'), value: 'ended' }
              ],
              onChange: function (value) {
                props.setAttributes({ status: value });
              }
            }),
            el(SelectControl, {
              label: __('Ending filter', 'community-auctions'),
              value: attrs.ending,
              options: [
                { label: __('Default', 'community-auctions'), value: '' },
                { label: __('Ending soon', 'community-auctions'), value: 'ending_soon' }
              ],
              onChange: function (value) {
                props.setAttributes({ ending: value });
              }
            }),
            el(TextControl, {
              label: __('Minimum bid', 'community-auctions'),
              value: attrs.minBid,
              onChange: function (value) {
                props.setAttributes({ minBid: value });
              }
            }),
            el(ToggleControl, {
              label: __('Show group badge', 'community-auctions'),
              checked: attrs.showGroupBadge,
              onChange: function (value) {
                props.setAttributes({ showGroupBadge: value });
              }
            })
          )
        ),
        el(serverSideRender, {
          key: 'preview',
          block: 'community-auctions/auctions-list',
          attributes: attrs
        })
      ];
    },
    save: function () {
      return null;
    }
  });
})(wp.blocks, wp.element, wp.i18n, wp.components, wp.blockEditor, wp.serverSideRender);

