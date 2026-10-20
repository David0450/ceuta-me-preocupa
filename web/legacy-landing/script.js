// Habilita el campo "zona" solo cuando la persona desmarca "No deseo indicarlo"
document.addEventListener('DOMContentLoaded', () => {
  const omitir = document.getElementById('zona-omitir');
  const zona = document.getElementById('zona');
  if (!omitir || !zona) return;

  omitir.addEventListener('change', () => {
    zona.disabled = omitir.checked;
    if (omitir.checked) zona.value = '';
  });
});
