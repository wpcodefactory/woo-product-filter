"use strict";
var g_wpfCurrTour = null
,	g_wpfTourOpenedWithTab = false
,	g_wpfAdminTourDissmissed = false;
jQuery(document).ready(function(){
	setTimeout(function(){
		if(typeof(wpfAdminTourData) !== 'undefined' && wpfAdminTourData.tour) {
			jQuery('body').append( wpfAdminTourData.html );
			wpfAdminTourData._$ = jQuery('#woobewoo-admin-tour');
			for(var tourId in wpfAdminTourData.tour) {
				if(wpfAdminTourData.tour[ tourId ].points) {
					for(var pointId in wpfAdminTourData.tour[ tourId ].points) {
						_wpfOpenPointer(tourId, pointId);
						break;	// Open only first one
					}
				}
			}
			for(var tourId in wpfAdminTourData.tour) {
				if(wpfAdminTourData.tour[ tourId ].points) {
					for(var pointId in wpfAdminTourData.tour[ tourId ].points) {
						if(wpfAdminTourData.tour[ tourId ].points[ pointId ].sub_tab) {
							var subTab = wpfAdminTourData.tour[ tourId ].points[ pointId ].sub_tab;
							jQuery('a[href="'+ subTab+ '"]')
								.data('tourId', tourId)
								.data('pointId', pointId);
							var tabChangeEvt = str_replace(subTab, '#', '')+ '_tabSwitch';
							jQuery(document).bind(tabChangeEvt, function(event, selector) {
								if(!g_wpfTourOpenedWithTab && !g_wpfAdminTourDissmissed) {
									var $clickTab = jQuery('a[href="'+ selector+ '"]');
									_wpfOpenPointer($clickTab.data('tourId'), $clickTab.data('pointId'));
								}
							});
						}
					}
				}
			}
		}
	}, 500);
});

function _wpfOpenPointerAndPopupTab(tourId, pointId, tab) {
	g_wpfTourOpenedWithTab = true;
	jQuery('#wpfPopupEditTabs').wpTabs('activate', tab);
	_wpfOpenPointer(tourId, pointId);
	g_wpfTourOpenedWithTab = false;
}
function _wpfOpenPointer(tourId, pointId) {
	var pointer = wpfAdminTourData.tour[ tourId ].points[ pointId ];
	var $content = wpfAdminTourData._$.find('#woobewoo-'+ tourId+ '-'+ pointId);
	if(!jQuery(pointer.target) || !jQuery(pointer.target).length)
		return;
	if(g_wpfCurrTour) {
		_wpfTourSendNext(g_wpfCurrTour._tourId, g_wpfCurrTour._pointId);
		g_wpfCurrTour.element.pointer('close');
		g_wpfCurrTour = null;
	}
	if(pointer.sub_tab && jQuery('#wpfPopupEditTabs').wpTabs('getActiveTab') != pointer.sub_tab) {
		return;
	}
	var options = jQuery.extend( pointer.options, {
		content: $content.find('.woobewoo-tour-content').html()
	,	pointerClass: 'wp-pointer woobewoo-pointer'
	,	close: function() {

		}
	,	buttons: function(event, t) {
			g_wpfCurrTour = t;
			g_wpfCurrTour._tourId = tourId;
			g_wpfCurrTour._pointId = pointId;
			var $btnsShell = $content.find('.woobewoo-tour-btns')
			,	$closeBtn = $btnsShell.find('.close')
			,	$finishBtn = $btnsShell.find('.woobewoo-tour-finish-btn');

			if($finishBtn && $finishBtn.length) {
				$finishBtn.click(function(e){
					e.preventDefault();
					jQuery.sendFormWpf({
						msgElID: 'noMessages'
					,	data: {mod: 'promo', action: 'addTourFinish', tourId: tourId, pointId: pointId}
					});
					g_wpfCurrTour.element.pointer('close');
				});
			}
			if($closeBtn && $closeBtn.length) {
				$closeBtn.bind( 'click.pointer', function(e) {
					e.preventDefault();
					jQuery.sendFormWpf({
						msgElID: 'noMessages'
					,	data: {mod: 'promo', action: 'closeTour', tourId: tourId, pointId: pointId}
					});
					t.element.pointer('close');
					g_wpfAdminTourDissmissed = true;
				});
			}
			return $btnsShell;
		}
	});
	jQuery(pointer.target).pointer( options ).pointer('open');
	var minTop = 10
	,	pointerTop = parseInt(g_wpfCurrTour.pointer.css('top'));
	if(!isNaN(pointerTop) && pointerTop < minTop) {
		g_wpfCurrTour.pointer.css('top', minTop+ 'px');
	}
}
function _wpfTourSendNext(tourId, pointId) {
	jQuery.sendFormWpf({
		msgElID: 'noMessages'
	,	data: {mod: 'promo', action: 'addTourStep', tourId: tourId, pointId: pointId}
	});
}