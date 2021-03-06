window.addEventListener('load', function () {
    var formUniqueCode = document.getElementById('formUniqueCode').value.trim(),
        widgetPreviewSubscribe = document.getElementById('widgetPreview'),
        widgetPreviewUnsubscribe = document.getElementById('widgetPreviewUnsubscribe'),
        nl2gStylesConfig = document.getElementById('nl2gStylesConfig');

    if (formUniqueCode) {
        var widgetStyleConfig = document.getElementById('widgetStyleConfig'),
            input,
            timer = 0,
            n2gSetUp = function  () {
                if (widgetStyleConfig.textContent === null || widgetStyleConfig.textContent.trim() === "") {
                    widgetStyleConfig.textContent = JSON.stringify(n2gConfig, null, 2);
                } else {
                    n2gConfig = JSON.parse(widgetStyleConfig.textContent);
                }

                [].forEach.call(document.getElementsByClassName('n2go-colorField'), function (element) {
                    var field = element.name.match(/[^.]+/g);
                    if (!field || !field[0]) return;
                    var style = '';
                    if (!n2gConfig && (typeof n2gConfig[field[0]]['style'] != 'undefined')) {
                        getStyle(field[1], n2gConfig[field[0]]['style']);
                    }

                    if (style !== '') {
                        style = style.replace('#','');
                        element.value = style;
                        element.focus();
                        element.blur();
                    }

                });
            };

        function getStyle (field, str) {
            var styleArray = str.split(';');

            for (var i=0; i < styleArray.length; i++){
                var styleField = styleArray[i].split(':');
                if (styleField[0].trim() == field) {
                    return styleField[1].trim();
                }
            }
            return '';
        }

        function updateConfig (element) {
            widgetStyleConfig.textContent = '';
            var formPropertyArray = element.name.match(/[^.]+/g);
                property = formPropertyArray[0],
                attribute = 'style',
                cssProperty = formPropertyArray[1],
                cssValue = '#' + element.value;

            var styleProperties;
            if (n2gConfig[property][attribute] == '') {
                styleProperties = cssProperty + ':' + cssValue;
            } else {
                styleProperties = updateString(n2gConfig[property][attribute], cssProperty, cssValue);
            }

            n2gConfig[property][attribute] = styleProperties;
            widgetStyleConfig.textContent = JSON.stringify(n2gConfig, null, 2);
        }

        function updateForm () {
            clearTimeout(timer);
            if (jQuery('#widgetPreview').length > 0) {
                timer = setTimeout(function () {
                    jQuery('#widgetPreview').find('form').remove();
                    n2g('subscribe:createForm', n2gConfig, 'n2g_script_subscribe');
                }, 100);
            }
            if (jQuery('#widgetPreviewUnsubscribe').length > 0) {
                timer = setTimeout(function () {
                    jQuery('#widgetPreviewUnsubscribe').find('form').remove();
                    n2g('unsubscribe:createForm', n2gConfig, 'n2g_script_unsubscribe');
                }, 100);
            }
        }

        function updateString (string, cssProperty, cssValue) {
            var stylePropertiesArray = string.match(/[^;]+/g),
                found = false,
                updatedString;
            // todo
            for (var i = 0; i < stylePropertiesArray.length-1; i++) {
                var trimmedAttr = stylePropertiesArray[i].trim();
                var styleProperty = trimmedAttr.split(':');
                if (styleProperty[0] == cssProperty) {
                    styleProperty[1] = cssValue;
                    stylePropertiesArray[i] = styleProperty[0] + ':' + styleProperty[1];
                    found = true;
                    break;
                }
            }
            if (!found) {
                stylePropertiesArray[i] = cssProperty + ':' + cssValue;
            }

            updatedString = stylePropertiesArray.join(';');

            if(updatedString.slice(-1) !== ';'){
                updatedString+=';';
            }

            return updatedString;
        }

        function show () {
            var btnConfig = jQuery('#btnShowConfig'),
                btnPreviewSubscribe = jQuery('#btnShowPreviewSubscribe'),
                btnPreviewUnsubscribe = jQuery('#btnShowPreviewUnsubscribe');

            jQuery('#n2gButtons li').removeClass('active');
            jQuery('#preview-form-panel > div').hide();

            switch (this.id) {
                case 'btnShowPreviewUnsubscribe':
                    widgetPreviewUnsubscribe.style.display = 'block';
                    btnPreviewUnsubscribe.addClass('active');
                    break;
                case 'btnShowPreviewSubscribe':
                    widgetPreviewSubscribe.style.display = 'block';
                    btnPreviewSubscribe.addClass('active');
                    break;
                default:
                    nl2gStylesConfig.style.display = 'block';
                    btnConfig.addClass('active');
                    break;
            }
        }

        n2gSetUp();

        n2g('create', formUniqueCode);
        
        if (jQuery('#widgetPreview').length > 0) {
            n2g('subscribe:createForm', n2gConfig, 'n2g_script_subscribe');
        }
        if (jQuery('#widgetPreviewUnsubscribe').length > 0) {
            n2g('unsubscribe:createForm', n2gConfig, 'n2g_script_unsubscribe');
        }

        // show();

        [].forEach.call(document.getElementById('n2gButtons').children, function (button) {
            button.addEventListener('click', show);
        });

        jQuery('.n2go-colorField').change( function() {
            input = this;

            updateConfig(input);
            updateForm();
        });
        document.getElementById("resetStyles").addEventListener("click", function(e){
            e.preventDefault();
            var defaultConfig = JSON.stringify(n2goConfigConst, null, 2);
            jQuery.ajax({
                type: 'POST',
                url: document.location.origin+Drupal.settings.basePath+ 'n2go/resetStyles',
                data :{
                    style: defaultConfig
                },
                success: function (data) {
                     location.reload();
                }
            });
        });
    }
});