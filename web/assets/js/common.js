//var modal_zindex = 1001;

$('body').on('hidden.bs.modal','.modal',  function () {
    $(this).removeClass('modal-open');
    $('.modal-backdrop').remove();
    $(this).removeData('bs.modal');
});

var RunBB = {
    init: function()
    {
        $(function()
        {
            RunBB.pageLoaded();
        });

        return true;
    },

    pageLoaded: function()
    {
        // Initialise "initial focus" field if we have one
        var initialfocus = $(".initial_focus");
        if(initialfocus.length)
        {
            initialfocus.focus();
        }

        if (typeof $.modal !== "undefined")
        {
            $(document).on($.modal.OPEN, function(event, modal) {
                $("body").css("overflow", "hidden");
                if(initialfocus.length > 0)
                {
                    initialfocus.focus();
                }
            });

            $(document).on($.modal.CLOSE, function(event, modal) {
                $("body").css("overflow", "auto");
                $(this).removeData('bs.modal');
            });
        }
    },

    popupWindow: function(url, options, root)
    {
        if(!options) options = {
            fadeDuration: 250,
            zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999)
        };
        if(root != true)
            url = baseUrl + url;

        $.get(url, function(html)
        {
            $(html).appendTo('body').modal(options);
        });
    },

    changeLanguage: function()
    {
        form = $("#lang_select");
        if(!form.length)
        {
            return false;
        }
        form.submit();
    },

    changeTheme: function()
    {
        form = $("#theme_select");
        if(!form.length)
        {
            return false;
        }
        form.submit();
    },

    selectCheckboxes: function(curFormId, link, new_string) {
        var curForm = document.getElementById(curFormId);
        var inputlist = curForm.getElementsByTagName("input");
        for (i = 0; i < inputlist.length; i++) {
            if (inputlist[i].getAttribute("type") == 'checkbox' && inputlist[i].disabled == false) {
                inputlist[i].checked = true;
            }
        }
        link.setAttribute('onclick', 'RunBB.unselectCheckboxes(\'' + curFormId + '\', this, \'' + link.innerHTML + '\')');
        link.innerHTML = new_string;

        return false;
    },

    unselectCheckboxes: function(curFormId, link, new_string) {
        var curForm = document.getElementById(curFormId);
        var inputlist = curForm.getElementsByTagName("input");
        for (i = 0; i < inputlist.length; i++) {
            if (inputlist[i].getAttribute("type") == 'checkbox' && inputlist[i].disabled == false) {
                inputlist[i].checked = false;
            }
        }
        link.setAttribute('onclick', 'RunBB.selectCheckboxes(\'' + curFormId + '\', this, \'' + link.innerHTML + '\')');
        link.innerHTML = new_string;

        return false;
    }
};

$('.toggler').on('click', function () {
    $(this).toggleClass('fa-rotate-180 on');
});

/* init tooltip */
$(function(){
    $('body').tooltip({ selector: '[data-toggle="tooltip"]' });
});


RunBB.init();
