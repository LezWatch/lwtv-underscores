/* @ds-bundle: {"format":4,"namespace":"LezWatch","components":[{"name":"Button"},{"name":"Badge"},{"name":"Card"},{"name":"Field"},{"name":"Alert"},{"name":"ScoreMeter"}]} */
(function () {
  var React = window.React;
  var h = React.createElement;
  function cx() { return Array.prototype.filter.call(arguments, Boolean).join(' '); }

  function Button(p) {
    var variant = p.variant || 'solid';
    var cls = cx('lw-btn', 'lw-btn--' + variant, p.className);
    if (p.href) {
      return h('a', { className: cls, href: p.href, onClick: p.onClick, 'aria-disabled': p.disabled ? 'true' : undefined }, p.children);
    }
    return h('button', { className: cls, type: p.type || 'button', disabled: p.disabled, onClick: p.onClick }, p.children);
  }

  function Badge(p) {
    var tone = p.tone || 'primary';
    var cls = cx('lw-badge', 'lw-badge--' + tone, p.className);
    if (p.href) return h('a', { className: cls, href: p.href }, p.children);
    return h('span', { className: cls }, p.children);
  }

  function Card(p) {
    return h('article', { className: cx('lw-card', p.className) },
      p.image ? h('img', { className: 'lw-card__img', src: p.image, alt: p.imageAlt || '' }) : null,
      p.title ? h('header', { className: 'lw-card__header' }, h('h3', { className: 'lw-card__title' }, p.title)) : null,
      h('div', { className: 'lw-card__body' }, p.children)
    );
  }

  function Field(p) {
    var kind = p.kind || 'text';
    var id = p.id;
    var invalid = !!p.error;
    var common = {
      id: id, name: p.name || id, className: 'lw-input', placeholder: p.placeholder, disabled: p.disabled,
      value: p.value, defaultValue: p.defaultValue, onChange: p.onChange,
      'aria-invalid': invalid ? 'true' : undefined,
      'aria-describedby': (invalid || p.hint) ? id + '-note' : undefined
    };
    var control;
    if (kind === 'select') {
      control = h('select', common, (p.options || []).map(function (o) { return h('option', { key: o, value: o }, o); }));
    } else if (kind === 'textarea') {
      control = h('textarea', Object.assign({ rows: 4 }, common));
    } else {
      control = h('input', Object.assign({ type: kind }, common));
    }
    return h('div', { className: cx('lw-field', p.className) },
      p.label ? h('label', { className: 'lw-field__label', htmlFor: id }, p.label) : null,
      control,
      (invalid || p.hint) ? h('p', { id: id + '-note', className: cx('lw-field__note', invalid && 'lw-field__note--error') }, invalid ? 'Error: ' + p.error : p.hint) : null
    );
  }

  var ALERT_WORD = { info: 'Note', success: 'Success', warning: 'Warning', danger: 'Error' };
  function Alert(p) {
    var tone = p.tone || 'info';
    return h('div', { className: cx('lw-alert', 'lw-alert--' + tone, p.className), role: tone === 'danger' ? 'alert' : 'status' },
      h('strong', { className: 'lw-alert__label' }, (p.label || ALERT_WORD[tone]) + ': '),
      p.children
    );
  }

  function ScoreMeter(p) {
    var has = p.score !== null && p.score !== undefined && p.score !== '';
    var n = has ? Math.max(0, Math.min(100, Math.round(Number(p.score)))) : null;
    if (n === null || isNaN(n)) {
      return h('div', { className: cx('lw-score', p.className) },
        h('span', { className: 'lw-score__label' }, p.label || 'Score'),
        h('span', { className: 'lw-score__na' }, 'N/A'));
    }
    return h('div', { className: cx('lw-score', p.className) },
      h('span', { className: 'lw-score__label' }, p.label || 'Score'),
      h('div', { className: 'lw-score__track', role: 'meter', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': n, 'aria-label': (p.label || 'Score') + ' out of 100' },
        h('span', { className: 'lw-score__fill', style: { width: n + '%' } })),
      h('span', { className: 'lw-score__num' }, n, h('span', { className: 'lw-score__of' }, ' / 100'))
    );
  }

  window.LezWatch = { Button: Button, Badge: Badge, Card: Card, Field: Field, Alert: Alert, ScoreMeter: ScoreMeter };
})();
