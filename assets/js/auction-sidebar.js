/* global wp */
/**
 * Community Auctions - Gutenberg Sidebar Panel
 *
 * Provides auction settings directly in the document sidebar for easier access.
 */
(function(plugins, editPost, components, data, element, i18n) {
    var registerPlugin = plugins.registerPlugin;
    var PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
    var useSelect = data.useSelect;
    var useDispatch = data.useDispatch;
    var el = element.createElement;
    var Fragment = element.Fragment;
    var __ = i18n.__;

    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var PanelRow = components.PanelRow;
    var DateTimePicker = components.DateTimePicker;
    var Dropdown = components.Dropdown;
    var Button = components.Button;
    var BaseControl = components.BaseControl;

    // Helper to format date for display
    function formatDateForDisplay(dateString) {
        if (!dateString) return __('Not set', 'community-auctions');
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return __('Not set', 'community-auctions');
        return date.toLocaleString();
    }

    // Helper to convert WP date to datetime-local format
    function toDateTimeLocal(dateString) {
        if (!dateString) return '';
        // If already in datetime-local format, return as-is
        if (dateString.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/)) {
            return dateString.substring(0, 16);
        }
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        return date.toISOString().substring(0, 16);
    }

    // Date picker button component
    function DatePickerButton(props) {
        var label = props.label;
        var value = props.value;
        var onChange = props.onChange;

        return el(
            BaseControl,
            { label: label, className: 'ca-sidebar-date-control' },
            el(
                Dropdown,
                {
                    position: 'bottom left',
                    renderToggle: function(toggleProps) {
                        return el(
                            Button,
                            {
                                onClick: toggleProps.onToggle,
                                isSecondary: true,
                                className: 'ca-sidebar-date-button'
                            },
                            value ? formatDateForDisplay(value) : __('Select date & time', 'community-auctions')
                        );
                    },
                    renderContent: function(contentProps) {
                        return el(
                            DateTimePicker,
                            {
                                currentDate: value ? new Date(value) : null,
                                onChange: function(newDate) {
                                    if (newDate) {
                                        onChange(toDateTimeLocal(newDate));
                                    }
                                    contentProps.onClose();
                                },
                                is12Hour: true
                            }
                        );
                    }
                }
            )
        );
    }

    // Main sidebar component
    function AuctionSidebar() {
        var postType = useSelect(function(select) {
            return select('core/editor').getCurrentPostType();
        }, []);

        // Only show for auction post type
        if (postType !== 'auction') {
            return null;
        }

        var meta = useSelect(function(select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);

        var editPost = useDispatch('core/editor').editPost;

        function updateMeta(key, value) {
            var newMeta = {};
            newMeta[key] = value;
            editPost({ meta: newMeta });
        }

        // Get currency symbol from localized data
        var currencySymbol = window.caAuctionSidebar && window.caAuctionSidebar.currencySymbol ? window.caAuctionSidebar.currencySymbol : '$';
        var buyNowEnabled = window.caAuctionSidebar && window.caAuctionSidebar.buyNowEnabled;

        return el(
            Fragment,
            null,
            // Schedule Panel
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'ca-auction-schedule',
                    title: __('Auction Schedule', 'community-auctions'),
                    icon: 'calendar-alt',
                    initialOpen: true
                },
                el(
                    PanelRow,
                    null,
                    el(DatePickerButton, {
                        label: __('Start Date & Time', 'community-auctions'),
                        value: meta.ca_start_at || '',
                        onChange: function(value) {
                            updateMeta('ca_start_at', value);
                        }
                    })
                ),
                el(
                    PanelRow,
                    null,
                    el(DatePickerButton, {
                        label: __('End Date & Time', 'community-auctions'),
                        value: meta.ca_end_at || '',
                        onChange: function(value) {
                            updateMeta('ca_end_at', value);
                        }
                    })
                )
            ),
            // Pricing Panel
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'ca-auction-pricing',
                    title: __('Pricing', 'community-auctions'),
                    icon: 'money-alt',
                    initialOpen: true
                },
                el(
                    PanelRow,
                    null,
                    el(TextControl, {
                        label: __('Starting Price', 'community-auctions') + ' (' + currencySymbol + ')',
                        type: 'number',
                        step: '0.01',
                        min: '0',
                        value: meta.ca_start_price || '',
                        onChange: function(value) {
                            updateMeta('ca_start_price', value);
                        },
                        placeholder: '0.00'
                    })
                ),
                el(
                    PanelRow,
                    null,
                    el(TextControl, {
                        label: __('Bid Increment', 'community-auctions') + ' (' + currencySymbol + ')',
                        type: 'number',
                        step: '0.01',
                        min: '0',
                        value: meta.ca_min_increment || '',
                        onChange: function(value) {
                            updateMeta('ca_min_increment', value);
                        },
                        placeholder: '1.00'
                    })
                ),
                el(
                    PanelRow,
                    null,
                    el(TextControl, {
                        label: __('Reserve Price (Optional)', 'community-auctions') + ' (' + currencySymbol + ')',
                        type: 'number',
                        step: '0.01',
                        min: '0',
                        value: meta.ca_reserve_price || '',
                        onChange: function(value) {
                            updateMeta('ca_reserve_price', value);
                        },
                        placeholder: '0.00',
                        help: __('Hidden minimum price', 'community-auctions')
                    })
                ),
                buyNowEnabled ? el(
                    Fragment,
                    null,
                    el(
                        PanelRow,
                        null,
                        el(ToggleControl, {
                            label: __('Enable Buy It Now', 'community-auctions'),
                            checked: !!meta.ca_buy_now_enabled,
                            onChange: function(value) {
                                updateMeta('ca_buy_now_enabled', value ? '1' : '');
                            }
                        })
                    ),
                    meta.ca_buy_now_enabled ? el(
                        PanelRow,
                        null,
                        el(TextControl, {
                            label: __('Buy It Now Price', 'community-auctions') + ' (' + currencySymbol + ')',
                            type: 'number',
                            step: '0.01',
                            min: '0',
                            value: meta.ca_buy_now_price || '',
                            onChange: function(value) {
                                updateMeta('ca_buy_now_price', value);
                            },
                            placeholder: '0.00'
                        })
                    ) : null
                ) : null
            ),
            // Settings Panel
            el(
                PluginDocumentSettingPanel,
                {
                    name: 'ca-auction-settings',
                    title: __('Auction Settings', 'community-auctions'),
                    icon: 'admin-settings',
                    initialOpen: false
                },
                el(
                    PanelRow,
                    null,
                    el(SelectControl, {
                        label: __('Visibility', 'community-auctions'),
                        value: meta.ca_visibility || 'public',
                        options: [
                            { label: __('Public - Anyone can bid', 'community-auctions'), value: 'public' },
                            { label: __('Group Only - Members only', 'community-auctions'), value: 'group_only' }
                        ],
                        onChange: function(value) {
                            updateMeta('ca_visibility', value);
                        }
                    })
                ),
                el(
                    PanelRow,
                    null,
                    el(ToggleControl, {
                        label: __('Enable Proxy Bidding', 'community-auctions'),
                        checked: !!meta.ca_proxy_enabled,
                        onChange: function(value) {
                            updateMeta('ca_proxy_enabled', value ? '1' : '');
                        },
                        help: __('Allow automatic bidding up to max amount', 'community-auctions')
                    })
                ),
                el(
                    PanelRow,
                    null,
                    el(TextControl, {
                        label: __('Payment Reminder (hours)', 'community-auctions'),
                        type: 'number',
                        min: '1',
                        value: meta.ca_payment_reminder_hours || '',
                        onChange: function(value) {
                            updateMeta('ca_payment_reminder_hours', value);
                        },
                        placeholder: '48',
                        help: __('Leave empty for default setting', 'community-auctions')
                    })
                )
            )
        );
    }

    // Register the plugin
    registerPlugin('community-auctions-sidebar', {
        render: AuctionSidebar,
        icon: 'hammer'
    });

})(
    wp.plugins,
    wp.editPost,
    wp.components,
    wp.data,
    wp.element,
    wp.i18n
);
