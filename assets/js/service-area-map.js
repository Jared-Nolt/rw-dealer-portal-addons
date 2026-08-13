/* jshint esversion: 8 */
/* global google, rwdpMap, jQuery */

(function ($) {
  'use strict';

  var mapApi = null;
  var radiusCircle = null;
  var activeDealerId = null;
  var MAX_SERVICE_AREA_FIT_ZOOM = 12;

  function getMapSettingsSource() {
    // Prefer the add-on's own localized globals (works regardless of whether core
    // still runs the rwdp_map_localized_data filter); fall back to rwdpMap for
    // older core versions that do.
    return window.rwdpaMapSettings || (typeof rwdpMap !== 'undefined' ? rwdpMap : null);
  }

  function getServiceAreaSettings() {
    var source = getMapSettingsSource();
    return {
      showInResults: !!(source && source.showServiceAreaInResults),
      showInPopup: !!(source && source.showServiceAreaInPopup),
      textTemplate: (source && source.serviceAreaTextTemplate) || '{value} mile service area'
    };
  }

  function getServiceAreaText(miles) {
    var settings = getServiceAreaSettings();
    return String(settings.textTemplate).replace('{value}', String(miles));
  }

  function getRadiusTexts() {
    var source = getMapSettingsSource();
    return {
      show: (source && source.showRadiusText) || 'Show Radius',
      hide: (source && source.hideRadiusText) || 'Hide Radius'
    };
  }

  function patchDealerServiceRadius(dealer) {
    if (!dealer || dealer.service_radius_miles !== undefined) {
      return;
    }

    var radii = window.rwdpaServiceRadii;
    if (!radii) {
      return;
    }

    var radius = radii[String(dealer.id)];
    dealer.service_radius_miles = radius ? Number(radius) : 0;
  }

  function clearRadiusCircle() {
    if (radiusCircle) {
      radiusCircle.setMap(null);
      radiusCircle = null;
    }
    activeDealerId = null;
    updateRadiusButtons();
  }

  function updateRadiusButtons() {
    var labels = getRadiusTexts();
    $('.rwdpa-service-radius-toggle').each(function () {
      var dealerId = Number($(this).data('dealer-id'));
      var isActive = activeDealerId !== null && Number(activeDealerId) === dealerId;
      $(this).text(isActive ? labels.hide : labels.show);
      $(this).attr('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function ensureRadiusButtons(dealers) {
    var dealerById = {};

    (dealers || []).forEach(function (dealer) {
      dealerById[String(dealer.id)] = dealer;
    });

    $('.rwdpa-service-radius-toggle').remove();

    $('.rwdp-result-card').each(function () {
      var $card = $(this);
      var dealerId = String($card.data('dealer-id') || '');
      var dealer = dealerById[dealerId];

      if (!dealer || !dealer.service_radius_miles || Number(dealer.service_radius_miles) <= 0) {
        return;
      }

      var $actions = $card.find('.rwdp-result-card__actions').first();
      if (!$actions.length) {
        var $body = $card.find('.rwdp-result-card__body').first();
        if (!$body.length) {
          return;
        }
        $actions = $('<div class="rwdp-result-card__actions"></div>');
        $body.append($actions);
      }

      $actions.append(
        $('<button type="button" class="rwdp-result-card__more-info rwdpa-service-radius-toggle"></button>')
          .attr('data-dealer-id', dealer.id)
      );
    });

    updateRadiusButtons();
  }

  function ensureServiceAreaValues(dealers) {
    var settings = getServiceAreaSettings();
    var dealerById = {};

    $('.rwdpa-service-area-value').remove();

    if (!settings.showInResults) {
      return;
    }

    (dealers || []).forEach(function (dealer) {
      dealerById[String(dealer.id)] = dealer;
    });

    $('.rwdp-result-card').each(function () {
      var $card = $(this);
      var dealerId = String($card.data('dealer-id') || '');
      var dealer = dealerById[dealerId];
      var miles = Number(dealer && dealer.service_radius_miles);

      if (!dealer || !miles || miles <= 0) {
        return;
      }

      var $body = $card.find('.rwdp-result-card__body').first();
      if (!$body.length) {
        return;
      }

      var $line = $('<div class="rwdp-result-card__service-area rwdpa-service-area-value"></div>').text(getServiceAreaText(miles));
      var $actions = $body.find('.rwdp-result-card__actions').first();
      if ($actions.length) {
        $line.insertBefore($actions);
      } else {
        $body.append($line);
      }
    });
  }

  function injectPopupServiceArea(dealer) {
    var settings = getServiceAreaSettings();
    var miles = Number(dealer && dealer.service_radius_miles);

    if (!settings.showInPopup || !miles || miles <= 0) {
      return;
    }

    setTimeout(function () {
      var $details = $('.gm-style .rwdp-infowindow .rwdp-infowindow__details').first();
      if (!$details.length || $details.find('.rwdpa-service-area-value-popup').length) {
        return;
      }

      $details.append(
        $('<p class="rwdp-infowindow__service-area rwdpa-service-area-value-popup"></p>').text(getServiceAreaText(miles))
      );
    }, 0);
  }

  function toggleRadiusCircle(dealerId) {
    if (!mapApi || !google || !google.maps) {
      return;
    }

    var map = mapApi.getMap();
    if (!map) {
      return;
    }

    if (activeDealerId !== null && Number(activeDealerId) === Number(dealerId)) {
      clearRadiusCircle();
      return;
    }

    var dealer = mapApi.getDealerById(dealerId);
    var marker = mapApi.getMarkerByDealerId(dealerId);
    if (!dealer || !marker || !dealer.service_radius_miles || Number(dealer.service_radius_miles) <= 0) {
      return;
    }

    if (radiusCircle) {
      radiusCircle.setMap(null);
      radiusCircle = null;
    }

    radiusCircle = new google.maps.Circle({
      strokeColor: '#1a5276',
      strokeOpacity: 0.85,
      strokeWeight: 2,
      fillColor: '#1a5276',
      fillOpacity: 0.18,
      map: map,
      center: marker.getPosition(),
      radius: Number(dealer.service_radius_miles) * 1609.344
    });

    map.panTo(marker.getPosition());
    activeDealerId = Number(dealerId);
    updateRadiusButtons();
  }

  function showRadiusCircleForDealer(dealerId) {
    if (!mapApi || !google || !google.maps) {
      return;
    }

    var map = mapApi.getMap();
    if (!map) {
      return;
    }

    var dealer = mapApi.getDealerById(dealerId);
    var marker = mapApi.getMarkerByDealerId(dealerId);
    if (!dealer || !marker || !dealer.service_radius_miles || Number(dealer.service_radius_miles) <= 0) {
      return;
    }

    if (radiusCircle) {
      radiusCircle.setMap(null);
      radiusCircle = null;
    }

    radiusCircle = new google.maps.Circle({
      strokeColor: '#1a5276',
      strokeOpacity: 0.85,
      strokeWeight: 2,
      fillColor: '#1a5276',
      fillOpacity: 0.18,
      map: map,
      center: marker.getPosition(),
      radius: Number(dealer.service_radius_miles) * 1609.344
    });

    var circleBounds = radiusCircle.getBounds();
    if (circleBounds) {
      map.fitBounds(circleBounds);
      google.maps.event.addListenerOnce(map, 'idle', function () {
        var currentZoom = Number(map.getZoom());
        if (currentZoom > MAX_SERVICE_AREA_FIT_ZOOM) {
          map.setZoom(MAX_SERVICE_AREA_FIT_ZOOM);
        }
      });
    } else {
      map.panTo(marker.getPosition());
    }

    activeDealerId = Number(dealerId);
    updateRadiusButtons();
  }

  $(document).on('rwdp:map-ready', function (event, api) {
    mapApi = api || mapApi;
  });

  // Registered first so dealer objects are patched (by reference) before the
  // handlers below and core's own dealer store read service_radius_miles.
  $(document).on('rwdp:results-rendered', function (event, api, dealers) {
    (dealers || []).forEach(patchDealerServiceRadius);
  });

  $(document).on('rwdp:dealer-selected', function (event, dealer) {
    patchDealerServiceRadius(dealer);
  });

  $(document).on('rwdp:results-rendered', function (event, api, dealers) {
    mapApi = api || mapApi;
    ensureRadiusButtons(dealers || []);
    ensureServiceAreaValues(dealers || []);

    if (activeDealerId !== null && mapApi && !mapApi.getDealerById(activeDealerId)) {
      clearRadiusCircle();
    }
  });

  $(document).on('rwdp:dealer-selected', function (event, dealer) {
    var miles = Number(dealer && dealer.service_radius_miles);
    if (!dealer || !miles || miles <= 0) {
      clearRadiusCircle();
    }
    injectPopupServiceArea(dealer || null);
  });

  $(document).on('rwdp:markers-updated', function (event, api) {
    mapApi = api || mapApi;

    if (activeDealerId === null || !mapApi) {
      return;
    }

    var marker = mapApi.getMarkerByDealerId(activeDealerId);
    if (!marker) {
      clearRadiusCircle();
      return;
    }

    if (radiusCircle) {
      radiusCircle.setCenter(marker.getPosition());
    }
  });

  $(document).on('click', '.rwdpa-service-radius-toggle', function () {
    var dealerId = Number($(this).data('dealer-id'));
    if (!dealerId) {
      return;
    }

    toggleRadiusCircle(dealerId);
  });

  $(document).on('click', '.rwdp-vom-btn', function () {
    var dealerId = Number($(this).data('dealer-id'));
    if (!dealerId) {
      return;
    }

    // If dealer has no radius, clear any active radius circle,
    // then let core View on Map behavior handle scroll + popup.
    var dealer = mapApi ? mapApi.getDealerById(dealerId) : null;
    if (!dealer || !dealer.service_radius_miles || Number(dealer.service_radius_miles) <= 0) {
      clearRadiusCircle();
      return;
    }

    showRadiusCircleForDealer(dealerId);
  });

}(jQuery));
