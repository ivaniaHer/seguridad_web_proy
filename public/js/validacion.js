/*
 * Validación del lado del cliente. Solo mejora la experiencia de uso:
 * el servidor vuelve a validar todo, porque esto se puede desactivar.
 * Va en un archivo aparte porque la CSP de nginx bloquea scripts en línea.
 */
document.addEventListener('DOMContentLoaded', function () {
  var formulario = document.querySelector('form[data-validar]');
  if (!formulario) return;

  var usuario = formulario.querySelector('#usuario');
  var contrasena = formulario.querySelector('#contrasena');
  var confirmacion = formulario.querySelector('#confirmacion');
  var esRegistro = formulario.hasAttribute('data-registro');

  function mensaje(campo, texto) {
    var destino = formulario.querySelector('.mensaje-campo[data-para="' + campo.id + '"]');
    campo.setCustomValidity(texto);
    campo.classList.toggle('invalido', texto !== '');
    if (destino) destino.textContent = texto;
  }

  function validarUsuario() {
    var v = usuario.value.trim();
    if (v === '') return mensaje(usuario, 'El usuario es obligatorio.');
    if (!/^[A-Za-z0-9_.]{3,30}$/.test(v)) {
      return mensaje(usuario, 'Usa de 3 a 30 caracteres: letras, números, punto o guion bajo.');
    }
    mensaje(usuario, '');
  }

  var reglas = {
    largo: function (p) { return p.length >= 8; },
    mayuscula: function (p) { return /[A-Z]/.test(p); },
    minuscula: function (p) { return /[a-z]/.test(p); },
    numero: function (p) { return /[0-9]/.test(p); },
    simbolo: function (p) { return /[^A-Za-z0-9]/.test(p); },
    usuario: function (p) {
      var u = usuario.value.trim().toLowerCase();
      return u === '' || p.toLowerCase().indexOf(u) === -1;
    }
  };

  function validarContrasena() {
    var p = contrasena.value;
    if (p === '') return mensaje(contrasena, 'La contraseña es obligatoria.');
    if (!esRegistro) return mensaje(contrasena, '');

    var todas = true;
    document.querySelectorAll('#requisitos li').forEach(function (li) {
      var cumple = reglas[li.dataset.regla](p);
      li.classList.toggle('cumple', cumple);
      todas = todas && cumple;
    });
    contrasena.setCustomValidity(todas ? '' : 'La contraseña no cumple la política.');
    contrasena.classList.toggle('invalido', !todas);
    if (confirmacion.value !== '') validarConfirmacion();
  }

  function validarConfirmacion() {
    mensaje(confirmacion, confirmacion.value === contrasena.value ? '' : 'Las contraseñas no coinciden.');
  }

  usuario.addEventListener('input', function () {
    validarUsuario();
    if (esRegistro && contrasena.value !== '') validarContrasena();
  });
  contrasena.addEventListener('input', validarContrasena);
  if (confirmacion) confirmacion.addEventListener('input', validarConfirmacion);

  formulario.addEventListener('submit', function (ev) {
    validarUsuario();
    validarContrasena();
    if (confirmacion) validarConfirmacion();
    if (!formulario.checkValidity()) {
      ev.preventDefault();
      var primero = formulario.querySelector(':invalid');
      if (primero) primero.focus();
      return;
    }
    // Evita doble envío.
    formulario.querySelector('button[type="submit"]').disabled = true;
  });
});
