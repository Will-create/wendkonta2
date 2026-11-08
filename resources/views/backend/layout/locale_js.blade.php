@php
    $frenchWeekdays = [__('db.Su'), __('db.Mo'), __('db.Tu'), __('db.We'), __('db.Th'), __('db.Fr'), __('db.Sa')];
    $frenchMonths = [__('db.month_1'), __('db.month_2'), __('db.month_3'), __('db.month_4'), __('db.month_5'), __('db.month_6'), __('db.month_7'), __('db.month_8'), __('db.month_9'), __('db.month_10'), __('db.month_11'), __('db.month_12')];
    $frenchMonthsShort = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
@endphp
<script>
(function () {
    var locale = @json(app()->getLocale());
    if (locale !== 'fr') {
        return;
    }
    if (window.jQuery && jQuery.fn && jQuery.fn.datepicker) {
        jQuery.fn.datepicker.dates.fr = {
            days: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
            daysShort: ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
            daysMin: ['D', 'L', 'M', 'M', 'J', 'V', 'S'],
            months: ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            monthsShort: ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
            today: "Aujourd'hui",
            clear: 'Effacer',
            format: 'dd-mm-yyyy',
            titleFormat: 'MM yyyy',
            weekStart: 1
        };
        jQuery.fn.datepicker.defaults.language = 'fr';
        jQuery.fn.datepicker.defaults.weekStart = 1;
    }
    if (window.jQuery && jQuery.validator) {
        jQuery.extend(jQuery.validator.messages, {
            required: @json(__('db.This field is required.')),
            remote: @json(__('db.Please fix this field.')),
            email: @json(__('db.Please enter a valid email address.')),
            url: @json(__('db.Please enter a valid URL.')),
            date: @json(__('db.Please enter a valid date.')),
            number: @json(__('db.Please enter a valid number.')),
            digits: @json(__('db.Please enter only digits.')),
            equalTo: @json(__('db.Please enter the same value again.')),
            maxlength: jQuery.validator.format(@json(__('db.Please enter no more than {0} characters.'))),
            minlength: jQuery.validator.format(@json(__('db.Please enter at least {0} characters.'))),
            max: jQuery.validator.format(@json(__('db.Please enter a value less than or equal to {0}.'))),
            min: jQuery.validator.format(@json(__('db.Please enter a value greater than or equal to {0}.')))
        });
    }
    if (window.moment) {
        moment.updateLocale('en', {
            months: @json($frenchMonths),
            monthsShort: @json($frenchMonthsShort),
            weekdays: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
            weekdaysShort: ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
            weekdaysMin: @json($frenchWeekdays),
            week: { dow: 1, doy: 4 }
        });
    }
    if (window.jQuery && jQuery.fn && jQuery.fn.daterangepicker) {
        var frenchRangeLocale = {
            cancelLabel: @json(__('db.Cancel')),
            applyLabel: @json(__('db.Apply')),
            applyButtonTitle: @json(__('db.Apply')),
            cancelButtonTitle: @json(__('db.Cancel')),
            customRangeLabel: @json(__('db.Custom Range')),
            daysOfWeek: @json($frenchWeekdays),
            monthNames: @json($frenchMonths),
            firstDay: 1
        };
        var periodTitles = {
            day: @json(__('db.Day')),
            week: @json(__('db.Week')),
            month: @json(__('db.Month')),
            quarter: @json(__('db.Quarter')),
            year: @json(__('db.Year'))
        };
        if (jQuery.fn.daterangepicker.Period) {
            jQuery.fn.daterangepicker.Period.title = function (period) {
                return periodTitles[period] || period;
            };
        }
        var originalDateRange = jQuery.fn.daterangepicker;
        jQuery.fn.daterangepicker = function (options, callback) {
            options = options || {};
            options.locale = jQuery.extend({}, frenchRangeLocale, options.locale || {});
            if (!options.locale.applyButtonTitle) {
                options.locale.applyButtonTitle = options.locale.applyLabel || frenchRangeLocale.applyButtonTitle;
            }
            if (!options.locale.cancelButtonTitle) {
                options.locale.cancelButtonTitle = options.locale.cancelLabel || frenchRangeLocale.cancelButtonTitle;
            }
            if (options.firstDayOfWeek == null) {
                options.firstDayOfWeek = 1;
            }
            if (!options.ranges && window.moment) {
                options.ranges = {};
                options.ranges[@json(__('db.Last 30 Days'))] = [moment().subtract(29, 'days'), moment()];
                options.ranges[@json(__('db.Last 90 Days'))] = [moment().subtract(89, 'days'), moment()];
                options.ranges[@json(__('db.Last Year'))] = [moment().subtract(1, 'year').add(1, 'day'), moment()];
                options.ranges[@json(__('db.All Time'))] = 'all-time';
                options.ranges[@json(__('db.Custom Range'))] = 'custom';
            }
            return originalDateRange.call(this, options, callback);
        };
    }
    if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
        jQuery.extend(true, jQuery.fn.dataTable.defaults, {
            language: {
                processing: @json(__('db.Processing...')),
                search: @json(__('db.Search')),
                lengthMenu: @json(__('db.Show _MENU_ entries')),
                info: @json(__('db.Showing _START_ to _END_ of _TOTAL_ entries')),
                infoEmpty: @json(__('db.Showing 0 to 0 of 0 entries')),
                infoFiltered: @json(__('db.filtered from _MAX_ total entries')),
                loadingRecords: @json(__('db.Loading...')),
                zeroRecords: @json(__('db.No matching records found')),
                emptyTable: @json(__('db.No data available in table')),
                paginate: {
                    first: @json(__('db.First')),
                    previous: @json(__('db.Previous')),
                    next: @json(__('db.Next')),
                    last: @json(__('db.Last'))
                },
                buttons: {
                    print: @json(__('db.Print')),
                    colvis: @json(__('db.Column visibility'))
                }
            }
        });
    }
})();
</script>
