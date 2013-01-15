var GraphManager = function() {};

GraphManager.prototype = {
    graphs: null,
    
    init: function(graph_img_selector) {    
        this.status_el = $('#aggregate-builder-status');
        this.initAggregateBuilder(graph_img_selector);

        this.status_el.on('click', 'a.show-agg-builder', (function(event) {
            event.preventDefault();
            this.aggregateBuilder.show();
        }).bind(this));

        this.status_el.on('click', 'a.reset-agg-builder', (function(event) {
            event.preventDefault();
            this.aggregateBuilder.reset();
            this.status_el.hide();
        }).bind(this));
    },

    getAggregateGraphData: function(aggParts, meta) {
        meta = meta || {};
        var data = {};
        meta.height = meta.height || 200;
        meta.width = meta.width || 500;
        $.extend(data, meta);
        data.host = aggParts.map(function(g) { return g.host; } ).join('|');
        data.rrdname = aggParts.map(function(g) { return g.rrdname; } ).join('|');
        data.subtype = aggParts.map(function(g) { return g.subtype; } ).join('|');
        data.color = aggParts.map(function(g) { return g.color; } ).join('|');
        data.graphing_method = aggParts.map(function(g) { return g.graphing_method; } ).join('|');
        data.custom_label = aggParts.map(function(g) { return g.custom_label; } ).join('|');
        data.count = aggParts.length;
        return data;
    },

    getDeserializedQueryString: function() {
        var data = {};
        var querystring = window.location.search;
        if (querystring && querystring != '') {
            var kvs = querystring.substring(1).split('&');
            var duration = ''
            kvs.map(function(e) {
                var kv = e.split('=');
                data[kv[0]] = kv.length ? kv[1] : '';
            });
        }
        return data;
    },

    getAggregateGraphUrl: function(aggParts, meta) {
        var data = this.getAggregateGraphData(aggParts, meta);
        
        var qs = this.getDeserializedQueryString();
        if (qs.duration) {
            data.duration = qs.duration;
        }
        return 'graph.php?' + Object.keys(data).map(function(k) { return [k, encodeURIComponent(data[k])].join('='); } ).join('&');
    },

    saveAggregate: function(aggParts, meta) {
        if (!aggParts) return false;
        var agg = {
            aggParts: aggParts,
            meta: meta
        };
        $.post('ajax/saveAggregate.php', this.getAggregateGraphData(aggParts, meta))
            .done(this.saveSuccess.bind(this))
            .fail(this.saveError.bind(this));
        this.aggregates.push(agg);
    },

    saveSuccess: function(data) {
        if (data.success) {
            this.aggregateBuilder.reset();
            this.aggregateBuilder.hide();
            var agg_id = data.aggregate_id;
            this.showStatusMessage('aggregate saved! <a href="viewaggregate.php?aggregate_id=' + agg_id + '">view aggregate</a> | <a href="aggregates.php">view all</a>', 8000);
        } else {
            this.saveError.apply(this, arguments);
        }
    },

    saveError: function() {
        console.error(arguments);
    },

    initAggregateBuilder: function(graph_img_selector) {
        var aggParts = null;
        if ('localStorage' in window && window['localStorage'] !== null) {
            aggParts = JSON.parse(localStorage.getItem("aggregateGraphParts"));
        }
        this.aggregateBuilder = new GraphManager.AggregateBuilder(this, aggParts, graph_img_selector);
        this.aggregateBuilder.init();
    },

    showStatusMessage: function(msg, duration) {
        this.status_el.find('.agg-builder-message').html(msg).show();
        this.status_el.find('.agg-builder-error').hide();
        this.status_el.removeClass('error').show();
        if (this.status_msg_timeout) {
            clearTimeout(this.status_msg_timeout);
            this.status_msg_timeout = null;
        }
        if (duration) {
            this.status_msg_timeout = setTimeout((function() {
                this.status_el.fadeOut();
            }).bind(this), duration);
        }
    },

    showStatusError: function(msg, duration) {
        this.status_el.find('.agg-builder-message').hide();
        this.status_el.find('.agg-builder-error').html(msg).show();
        this.status_el.addClass('error').show();
        if (this.status_msg_timeout) {
            clearTimeout(this.status_msg_timeout);
            this.status_msg_timeout = null;
        }
        if (duration) {
            this.status_msg_timeout = setTimeout((function() {
                this.status_el.fadeOut((function() {
                    this.onAggregateBuilderUpdate(this.aggregateBuilder.aggParts.length);
                }).bind(this));
            }).bind(this), duration);
        }
    },

    onAggregateBuilderUpdate: function(numAggParts) {
        if (numAggParts > 0) {
            this.showStatusMessage(
                '<a href="#" class="show-agg-builder">' +
                    'show aggregate builder (' + numAggParts + ' graph' + (numAggParts == 1 ? '' : 's') + ')' +
                '</a> | ' +
                '<a href="#" class="reset-agg-builder">reset</a>'
            );
        }
        this.status_el.toggle(numAggParts > 0);
    },

    onAggregateBuilderError: function(errorMsg) {
        this.showStatusError(errorMsg, 8000);
    }

};

GraphManager.AggregateBuilder = function(graphManager, aggParts, graph_img_selector) {
    this.graphManager = graphManager;
    this.aggParts = aggParts || [];
    this.inited = false;
    this.graph_img_selector = graph_img_selector;
};

GraphManager.AggregateBuilder.prototype = {
    init: function() {
        $(this.graph_img_selector).click((function(event) {
            if (event.shiftKey) {
                event.preventDefault();
                this.addToAggregate($(event.target));
            }
        }).bind(this))
            .attr('title',  'shift-click to add to aggregate');

        this.overlay = $('#aggregate-graph-overlay');
        this.overlay.on('click', '.close-icon', this.hide.bind(this));
        this.overlay.on('click', '#add-aggregate-link', this.addGraphForm.bind(this));

        this.overlay.on('click', '#do-update-aggregate', this.update.bind(this));
        this.overlay.on('click', '#do-save-aggregate', this.save.bind(this));
        this.overlay.on('click', '#do-reset-aggregate', this.reset.bind(this));

        this.overlay.on('change', '#aggregate-type-selector input', this.changeType.bind(this));

        this.forms = [];
        this.type = this.aggParts.length > 0 ? this.aggParts[0].type : null;

        $.each(this.aggParts, (function (i, aggPart) {
            this.addGraphForm(aggPart);
        }).bind(this));
        this.inited = true;
        this.updateUI();
        this.graphManager.onAggregateBuilderUpdate(this.aggParts.length);
    },

    addToAggregate: function(img) {
        var data = this.extractGraphData(img.attr('src'));
        if (data.type != null && (!this.type || data.type == this.type)) {
            this.type = data.type;
            this.aggParts.push(data);
            this.saveAggParts();
            this.addGraphForm(data);
            this.updateUI();
            this.graphManager.onAggregateBuilderUpdate(this.aggParts.length);
        } else {
            this.graphManager.onAggregateBuilderError('please select a graph of the same type as the others in this aggregate (' + this.type + ')');
        }
    },

    extractGraphData: function(url) {
        var kvs = url.replace('graph.php?', '').split('&');
        var data = {};
        $.each(kvs, function(i, kvp) {
            var kv = kvp.split('=');
            data[kv[0]] = kv.length > 0 ? kv[1] : '';
        });
        return data;
    },

    addGraphForm: function(data) {
        var form = new GraphManager.GraphEditForm(this.type, data, this.forms.length);
        this.overlay.find('#graph-forms').append(form.getForm());
        this.forms.push(form);
        this.updateUI(false);
        return form;
    },

    update: function() {
        this.aggParts = this.forms.filter(function(el) { return el.enabled; } ).map(function(f) {
            return f.getData();
        });
        this.saveAggParts();
        this.updateUI();
        this.graphManager.onAggregateBuilderUpdate(this.aggParts.length);
    },

    show: function() {
        this.overlay.show();
    },

    hide: function() {
        this.overlay.hide();
    },

    updateUI: function(redraw) {
        if (!this.inited) return;
        var hasAggParts = this.aggParts.length > 0;
        var hasType = !!this.type;
        var hasAggForms = this.forms.filter(function(f) { return f.enabled; }).length > 0;
        if (redraw !== false) {
            if (hasAggParts) {
                var imgUrl = this.graphManager.getAggregateGraphUrl(this.aggParts, this.getMeta());
                this.overlay.find('#aggregate-img').html($('<img>').error(this.imgError.bind(this)).attr('src', imgUrl)).show();
            } else {
                this.overlay.find('#aggregate-img').empty().hide();
            }
        }

        var aggTypeSelector = this.overlay.find('#aggregate-type-selector');
        
        aggTypeSelector.find('input').attr('disabled', hasAggParts || hasAggForms ? 'disabled' : null).attr('checked', null);
        if (hasType) {
            aggTypeSelector.find('input[value="' + this.type + '"]').attr('checked', 'checked');
        }
        
        this.overlay.find('#add-aggregate-link').toggle(hasType);
        this.overlay.find('.meta-field').toggle(hasAggForms);

        $('#aggregate-builder-status').toggle(hasAggParts);
    },

    getMeta: function() {
        var meta = {};
        meta.friendlytitle = $.trim(this.overlay.find('#edit-aggregate-tools input#aggregate-title').val());
        meta.stack = this.overlay.find('#edit-aggregate-tools input#aggregate-stack').is(':checked');
        meta.type = this.type;
        return meta;
    },

    imgError: function() {
        this.overlay.find('#aggregate-img').html($('<span class="error">There was a problem loading this graph.</span>'));
    },

    saveAggParts: function() {
        if ('localStorage' in window && window['localStorage'] !== null) {
            localStorage.setItem("aggregateGraphParts", JSON.stringify(this.aggParts));
        }
    },

    save: function() {
        this.graphManager.saveAggregate(this.aggParts, this.getMeta());
    },

    changeType: function() {
        var selectedType = this.overlay.find('#aggregate-type-selector input:checked').val();
        this.type = selectedType;
        this.updateUI();
    },

    reset: function() {
        $.each(this.forms, function(i, f) {
            f.destroy();
        });
        this.forms = [];
        this.aggParts = [];
        this.type = null;
        this.overlay.find('#edit-aggregate-tools input#aggregate-title').val('');
        this.overlay.find('#edit-aggregate-tools input#aggregate-stack').attr('checked', 'checked');
        this.update();
    }
};

GraphManager.GraphEditForm = function(type, data, idx) {
    this.type = type;
    this.data = data || {};
    this.idx = idx;
    this.portsByHost = {};
    this.form = this.createForm();
    this.form.find('.edit-graph-form-host').on("autocompletechange change", (function() {
        this.form.find('.edit-graph-form-rrdname').val('');
    }).bind(this));
    this.form.find('.remove-link').on("click", (function() {
        this.destroy();
    }).bind(this));
    this.enabled = true;
};

GraphManager.GraphEditForm.prototype = {
    getHosts: function() {
        if (GraphManager.HOSTS) {
            return Object.keys(GraphManager.HOSTS);
        }
        return [];
    },

    getRRDNames: function(request, response) {
        var host = this.getData().host;
        if (host && host != '' && this.type) {
            if (this.portsByHost[host]) {
                response(this.portsByHost[host].filter(function(el) { return el.match(new RegExp(request.term, "i")); }));
            } else {
                $.getJSON('ajax/getRRDNamesForHost.php', {host: host, type: this.type})
                    .done((function(data) {
                        this.portsByHost[host] = data;
                        response(this.portsByHost[host].filter(function(el) { return el.match(new RegExp(request.term, "i")); }));
                    }.bind(this)))
                    .fail(function() {
                        console.error(arguments); 
                        response([]);
                    });
            }
        } else {
            response([]);
        }
    },

    getSubtypes: function() {
        if (this.type != null) {
            return {
                bits: ['bits_in', 'bits_out'],
                ucastpkts: ['ucastpkts_in', 'ucastpkts_out'],
                errors: ['discards_in', 'errors_in', 'discards_out', 'errors_out'],
                mcastpkts: ['mcastpkts_in', 'mcastpkts_out'],
                bcastpkts: ['bcastpkts_in', 'bcastpkts_out']
            }[this.type];
        }
        return [];
    },

    getData: function() {
        var data = {};
        data.host = this.form.find('.edit-graph-form-host').val();    
        data.rrdname = this.form.find('.edit-graph-form-rrdname').val();    
        data.subtype = this.form.find('.edit-graph-form-subtype').val();    
        data.color = this.form.find('.edit-graph-form-color').val();
        data.graphing_method = this.form.find('.edit-graph-form-graphing-method').val();
        data.custom_label = this.form.find('.edit-graph-form-custom-label').val();
        data.type = this.type;
        return data;
    },

    getDefaultColor: function() {
        // these should match the colors in functions.php
        var colors = {   
            'bits': ['#0A2868', '#FFCA00', '#EC4890', '#517CD7', '#00C169', '#D1F94C'],
            'ucastpkts': ['#2008E6', '#D401E2', '#00DFD6', '#08004E'],
            'errors': ['#30B6C9', '#FFFE39', '#AD34CF', '#09616D'],
            'mcastpkts': ['#53B0B8', '#7E65C7', '#C2F2C6', '#FFB472'],
            'bcastpkts': ['#FFEF9F', '#C17AC3', '#7FA1C3', '#AA9737']
        }[this.type];
        return colors[this.idx % colors.length];
    },

    createForm: function() {
        var form = $('<div class="edit-graph-form" />');
        form.append($('<span class="remove-link">x</span>'));
        form.append($('<input class="edit-graph-form-host">').autocomplete({ source: this.getHosts() }).val(this.data.host || '').attr('placeholder', 'host'));
        form.append($('<input class="edit-graph-form-rrdname">').autocomplete({ source: (function (request, response) { this.getRRDNames(request, response) }).bind(this) }).val(this.data.rrdname || '').attr('placeholder', 'rrdname'));
        var subtype_select = $('<select class="edit-graph-form-subtype" />');
        subtype_select.append($('<option>').val('').attr('selected', !this.data.subtype));
        $.each(this.getSubtypes(), (function(i, subtype) {
            subtype_select.append($('<option>').val(subtype).attr('selected', (subtype == this.data.subtype || (i == 0 && !this.data.subtype)) ? 'selected' : null).text(subtype));
        }).bind(this));
        form.append(subtype_select);

        var graphing_method_select  = $('<select class="edit-graph-form-graphing-method" />');
        graphing_method_select.append($('<option>').val('AREA').attr('selected', !this.data.graphing_method || this.data.graphing_method == 'AREA').text('area'));
        graphing_method_select.append($('<option>').val('LINE1').attr('selected', this.data.graphing_method == 'LINE1').text('line'));
        form.append(graphing_method_select);

        form.append($('<input class="edit-graph-form-color" type="color" placeholder="#color">').val(this.data.color || this.getDefaultColor()));
        form.append($('<input class="edit-graph-form-custom-label">').val(this.data.custom_label || '').attr('placeholder', 'custom label'));

        form.on('change', '.edit-graph-form-host', function(){ form.find('.edit-graph-form-rrdname').empty(); });

        return form;
    },

    getForm: function() {
        return this.form;
    },

    destroy: function() {
        this.form.remove();
        this.enabled = false;
    }
};
