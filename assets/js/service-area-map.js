/* jshint esversion: 8 */
/* global google, rwdpMap, jQuery */

(function ($) {
  'use strict';

  var mapApi = null;
  var radiusCircle = null;
  var activeDealerId = null;

  function getRadiusTexts() {
    return {
      show: (rwdpMap && rwdpMap.showRadiusText) || 'Show Radius',
      hide: (rwdpMap && rwdpMap.hideRadiusText) || 'Hide Radius'
    };
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

    map.panTo(marker.getPosition());
    activeDealerId = Number(dealerId);
    updateRadiusButtons();
  }

  $(document).on('rwdp:map-ready', function (event, api) {
    mapApi = api || mapApi;
  });

  $(document).on('rwdp:results-rendered', function (event, api, dealers) {
    mapApi = api || mapApi;
    ensureRadiusButtons(dealers || []);

    if (activeDealerId !== null && mapApi && !mapApi.getDealerById(activeDealerId)) {
      clearRadiusCircle();
    }
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

    showRadiusCircleForDealer(dealerId);
  });

}(jQuery));
