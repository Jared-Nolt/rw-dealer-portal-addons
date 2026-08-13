/* jshint esversion: 8 */
/* global jQuery */

(function ($) {
  'use strict';

  var mapApi = null;

  $(document).on('rwdp:map-ready', function (event, api) {
    mapApi = api || mapApi;
  });

  function getVisibleDealerIds() {
    if (!mapApi) {
      return [];
    }

    var ids = [];
    var markers = typeof mapApi.getMarkers === 'function' ? mapApi.getMarkers() : [];

    if (markers && markers.length) {
      markers.forEach(function (marker) {
        if (marker && marker.dealerData && marker.dealerData.id) {
          ids.push(parseInt(marker.dealerData.id, 10));
        }
      });
    } else {
      var dealers = typeof mapApi.getDealers === 'function' ? mapApi.getDealers() : [];
      dealers.forEach(function (dealer) {
        if (dealer && dealer.id) {
          ids.push(parseInt(dealer.id, 10));
        }
      });
    }

    return ids.filter(function (id, idx, arr) {
      return id && arr.indexOf(id) === idx;
    });
  }

  function getOptionLabel($select, value) {
    var label = '';
    var target = String(value);

    $select.find('option').each(function () {
      if (String($(this).val()) === target) {
        label = $.trim($(this).text());
        return false;
      }
      return undefined;
    });

    return label;
  }

  function addUniqueLabel(store, seen, label) {
    var clean = $.trim(String(label || ''));
    if (!clean) {
      return;
    }

    var key = clean.toLowerCase();
    if (seen[key]) {
      return;
    }

    seen[key] = true;
    store.push(clean);
  }

  function getActiveFilterLabelText() {
    var labels = [];
    var seen = {};

    var $tax = $('#rwdp-tax-filter');
    if ($tax.length) {
      var taxVal = $tax.val();
      if (taxVal) {
        addUniqueLabel(labels, seen, getOptionLabel($tax, taxVal));
      }
    }

    $('.rwdp-acf-filter').each(function () {
      var $select = $(this);
      var selected = $select.val();
      var values = [];

      if (Array.isArray(selected)) {
        values = selected;
      } else if (selected) {
        values = [selected];
      }

      values.forEach(function (value) {
        if (!value) {
          return;
        }
        addUniqueLabel(labels, seen, getOptionLabel($select, value));
      });
    });

    return labels.join(' + ');
  }

  function setParam(url, key, value) {
    var parsed;
    try {
      parsed = new URL(url, window.location.href);
      if (value) {
        parsed.searchParams.set(key, value);
      } else {
        parsed.searchParams.delete(key);
      }
      return parsed.toString();
    } catch (e) {
      return url;
    }
  }

  function getCurrentDealerTypeSlug(baseUrl) {
    var $tax = $('#rwdp-tax-filter');
    if ($tax.length && $tax.val()) {
      return String($tax.val());
    }

    try {
      var parsed = new URL(baseUrl, window.location.href);
      return parsed.searchParams.get('dealer_type') || '';
    } catch (e) {
      return '';
    }
  }

  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest
      ? event.target.closest('#rwdp-print-results-btn')
      : null;

    if (!button) {
      return;
    }

    var $button = $(button);
    var baseUrl = $button.data('print-base') || $button.attr('href') || '';
    if (!baseUrl) {
      return;
    }

    var dealerType = getCurrentDealerTypeSlug(baseUrl);
    var filterLabelText = getActiveFilterLabelText();
    var visibleIds = getVisibleDealerIds();

    var nextUrl = setParam(baseUrl, 'dealer_type', dealerType);
    nextUrl = setParam(nextUrl, 'filter_title_display', filterLabelText);
    if (visibleIds.length) {
      nextUrl = setParam(nextUrl, 'dealer_ids', visibleIds.join(','));
    }

    $button.data('print-base', nextUrl);
    $button.attr('data-print-base', nextUrl);
    $button.attr('href', nextUrl);
  }, true);

}(jQuery));
