/* global jquery, moment, window */

/**
 * A server side countdown
 */
;
(function ($, moment, window) {

    // https://github.com/uxitten/polyfill/blob/master/string.polyfill.js
    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/padStart
    if (!String.prototype.padStart) {
        String.prototype.padStart = function padStart(targetLength, padString) {
            targetLength = targetLength >> 0; //truncate if number or convert non-number to 0;
            padString = String((typeof padString !== 'undefined' ? padString : ' '));
            if (this.length > targetLength) {
                return String(this);
            } else {
                targetLength = targetLength - this.length;
                if (targetLength > padString.length) {
                    padString += padString.repeat(targetLength / padString.length); //append to original to ensure we are longer than needed
                }
                return padString.slice(0, targetLength) + String(this);
            }
        };
    }

    $.fn.extend({
        ServerCountdown: function (options) {
            this.defaultOptions = {
                onInit: null,
                onTick: null,
                onComplete: null,
                reloadOnComplete: false,
                labels: {
                    days: 'd',
                    hours: 'h',
                    minutes: 'm',
                    seconds: 's'
                },
                // in ms
                interval: 1000,
                serverSyncUrl: '/__time',
                // in seconds
                serverPoll: 30
            };

            var settings = $.extend({}, this.defaultOptions, options);

            return this.each(function () {
                var $this = $(this);

                var start = $this.data('start');
                var end = $this.data('end');
                var url = $this.data('url');

                if (!start || !end) {
                    console.log("Must define data-start and data-end");
                    return;
                }

                var startDate = moment(start);
                var endDate = moment(end);

                // diff in milliseconds
                var data = {};
                data.diff = endDate.diff(startDate);
                var poll = settings.serverPoll;

                if (settings.onInit) {
                    settings.onInit.call();
                }

                var interval = setInterval(function () {
                    if (data.diff <= 0) {
                        clearInterval(interval);
                        if (settings.onComplete) {
                            settings.onComplete.call();
                        }
                        $this.text("00" + settings.labels.seconds);
                        if (settings.reloadOnComplete) {
                            window.location.reload();
                        } else if (url) {
                            window.location.replace(url);
                        }
                        return;
                    }

                    data.days = Math.floor(data.diff / (1000 * 60 * 60 * 24));
                    data.hours = Math.floor((data.diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    data.minutes = Math.floor((data.diff % (1000 * 60 * 60)) / (1000 * 60));
                    data.seconds = Math.floor((data.diff % (1000 * 60)) / 1000);

                    var parts = [];
                    if (data.days) {
                        parts.push(data.days + settings.labels.days);
                    }
                    parts.push(data.hours.toString().padStart(2, "0") + settings.labels.hours);
                    parts.push(data.minutes.toString().padStart(2, "0") + settings.labels.minutes);
                    parts.push(data.seconds.toString().padStart(2, "0") + settings.labels.seconds);

                    data.msg = parts.join(' ');
                    $this.text(data.msg);

                    if (settings.onTick) {
                        settings.onTick.call($this, data);
                    }

                    data.diff -= settings.interval;

                    // Check if needs polling (no within last 3 seconds)
                    poll--;
                    if (poll <= 0 && data.diff > 3000) {
                        poll = settings.serverPoll;

                        $.getJSON(settings.serverSyncUrl, function (result) {
                            startDate = moment(result.date);
                            var newDiff = endDate.diff(startDate);
                            // It can only go down
                            if (newDiff < data.diff) {
                                data.diff = newDiff;
                            }
                        });
                    }
                }, settings.interval);
            });
        }
    });
})(jQuery, moment, window);
