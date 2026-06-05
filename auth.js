(function(){
  var PASS = 'roman';
  var KEY  = 'site_auth';
  if(sessionStorage.getItem(KEY) === '1') return;
  var pwd = prompt('Sisestage parool / Введите пароль:');
  if(pwd === PASS){
    sessionStorage.setItem(KEY, '1');
  } else {
    document.documentElement.innerHTML = '';
    location.replace('about:blank');
  }
})();
