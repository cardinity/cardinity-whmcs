<?php


add_hook('ClientAreaFooterOutput', 1, function($vars) {
    
    //return "DID IT".print_r($vars['systemurl'], true);
    $browserInfo = "
    <form>
    <input type='hidden' class='crdBrowserInfo' form='frmCheckoutB' id='screen_width' name='browser_info[screen_width]' value='' />
    <input type='hidden' class='crdBrowserInfo' form='frmCheckoutB' id='screen_height' name='browser_info[screen_height]' value='' />
    <input type='hidden' class='crdBrowserInfo' form='frmCheckoutB' id='challenge_window_size' name='browser_info[challenge_window_size]' value='' />
    <input type='hidden' class='crdBrowserInfo' form='frmCheckoutB' id='browser_language' name='browser_info[browser_language]' value='' />
    <input type='hidden' class='crdBrowserInfo' form='frmCheckoutB' id='color_depth' name='browser_info[color_depth]' value='' />
    <input type='hidden' class='crdBrowserInfo' form='frmCheckoutB' id='time_zone' name='browser_info[time_zone]' value='' />
    </form>

    <script type='text/javascript'>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('screen_width').value = window.innerWidth;
            document.getElementById('screen_height').value = window.innerHeight;
            document.getElementById('browser_language').value = navigator.language;
            document.getElementById('color_depth').value = screen.colorDepth;
            document.getElementById('time_zone').value = new Date().getTimezoneOffset();

            var availChallengeWindowSizes = [
                [600, 400],
                [500, 600],
                [390, 400],
                [250, 400]
            ];

            var cardinity_screen_width = window.innerWidth;
            var cardinity_screen_height = window.innerHeight;

            document.getElementById('challenge_window_size').value = 'full-screen';

            //display below 800x600        
            if (!(cardinity_screen_width > 800 && cardinity_screen_height > 600)) {                        
                //find largest acceptable size
                availChallengeWindowSizes.every(function(element, index) {
                    console.log(element);
                    if (element[0] > cardinity_screen_width || element[1] > cardinity_screen_height) {
                        //this challenge window size is not acceptable
                        return true;
                    } else {
                        document.getElementById('challenge_window_size').value = element[0]+'x'+element[1];
                        return false;
                    }        
                });
            }

            saveBrowserInfo();

        });

        function saveBrowserInfo()
        {
            var elements = document.getElementsByClassName('crdBrowserInfo');
            var formData = new FormData(); 
            for(var i=0; i<elements.length; i++)
            {
                formData.append(elements[i].name, elements[i].value);
            }
            var xmlHttp = new XMLHttpRequest();
                xmlHttp.onreadystatechange = function()
                {
                    if(xmlHttp.readyState == 4 && xmlHttp.status == 200)
                    {
                        console.log('browser info sent');
                    }
                }
                xmlHttp.open('post', '".$vars['systemurl'] . 'modules/gateways/callback/cardinitybrowserinfo.php'."'); 
                xmlHttp.send(formData); 
        }
    </script>   
    ";   

    return $browserInfo;
   
});