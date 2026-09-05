(function () {
  'use strict';

  /* 危险操作（删除）前的确认弹窗 */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  function humanSize(bytes) {
    if (bytes >= 1048576) return (Math.round(bytes / 104857.6) / 10) + ' MB';
    if (bytes >= 1024) return (Math.round(bytes / 102.4) / 10) + ' KB';
    return bytes + ' B';
  }

  function formError(form, message) {
    var box = form.querySelector('.js-form-error');
    if (!box) return;
    if (message) {
      box.textContent = message;
      box.hidden = false;
    } else {
      box.hidden = true;
      box.textContent = '';
    }
  }

  function setInputFiles(input, keepIndexes) {
    if (typeof DataTransfer === 'undefined') return; // 极老浏览器：仅提示，不能移除单个
    var dt = new DataTransfer();
    Array.prototype.forEach.call(input.files, function (file, i) {
      if (keepIndexes.indexOf(i) !== -1) dt.items.add(file);
    });
    input.files = dt.files;
  }

  /* ============ 发布/编辑框：图片 + 附件选择（大小/类型前端即时校验 + 预览） ============ */
  document.querySelectorAll('form').forEach(function (form) {
    var inputs = Array.prototype.slice.call(form.querySelectorAll('.js-file-input'));
    if (!inputs.length) return;
    var grid = form.querySelector('.js-preview-grid');
    var maxAtt = parseInt(form.getAttribute('data-max-att') || '0', 10);

    /* 提交前最后校验（防止用户改了文件之后没触发 change 之类边缘情况） */
    form.addEventListener('submit', function (event) {
      var attCount = 0;
      var problem = '';
      inputs.forEach(function (input) {
        var max = parseInt(input.getAttribute('data-max') || '0', 10);
        Array.prototype.forEach.call(input.files || [], function (file) {
          if (max && file.size > max) {
            problem = '「' + file.name + '」' + humanSize(file.size) + '，文件过大，当前最大允许 ' + humanSize(max) + '。';
          }
          if (input.getAttribute('data-kind') !== 'image') attCount++;
        });
      });
      if (!problem && maxAtt && attCount > maxAtt) {
        problem = '附件太多：每条动态最多 ' + maxAtt + ' 个（视频+文件），当前选了 ' + attCount + ' 个。';
      }
      if (problem) {
        event.preventDefault();
        formError(form, problem);
      }
    });

    /* 每个输入选择文件时：立即校验大小，即时预览 */
    inputs.forEach(function (input) {
      var kind = input.getAttribute('data-kind') || 'file';
      var max = parseInt(input.getAttribute('data-max') || '0', 10);

      var render = function () {
        formError(form, '');
        if (!grid) return;
        grid.querySelectorAll('.js-dyn-preview').forEach(function (node) { node.remove(); });
        var urls = [];
        Array.prototype.forEach.call(input.files || [], function (file, index) {
          var invalid = max && file.size > max;
          if (invalid) {
            formError(form, '「' + file.name + '」' + humanSize(file.size) + '，文件过大，当前最大允许 ' + humanSize(max) + '。该文件不会随表单上传，请移除后重试。');
          }
          var cell = document.createElement('div');
          cell.className = 'js-dyn-preview';
          if (kind === 'image' && /^image\//i.test(file.type)) {
            cell.className += ' preview-cell';
            var img = document.createElement('img');
            var url = URL.createObjectURL(file);
            urls.push(url);
            img.src = url;
            img.alt = '';
            cell.appendChild(img);
          } else {
            cell.className += ' preview-chip';
            var icon = document.createElement('span');
            icon.textContent = kind === 'video' ? '🎬' : '📎';
            var name = document.createElement('span');
            name.className = 'preview-chip-name';
            name.textContent = file.name;
            var size = document.createElement('span');
            size.className = 'preview-chip-size';
            size.textContent = humanSize(file.size);
            cell.appendChild(icon);
            cell.appendChild(name);
            cell.appendChild(size);
          }
          if (invalid) cell.classList.add('preview-invalid');
          var remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'preview-remove';
          remove.textContent = '\u00d7';
          remove.setAttribute('aria-label', '移除该文件');
          remove.addEventListener('click', function () {
            var keep = [];
            Array.prototype.forEach.call(input.files, function (f, i) {
              if (i !== index) keep.push(i);
            });
            setInputFiles(input, keep);
            render();
          });
          cell.appendChild(remove);
          grid.appendChild(cell);
        });
      };
      input.addEventListener('change', render);
    });
  });

  /* ============ 头像：选择后即时校验 + 本地预览 ============ */
  document.querySelectorAll('.js-avatar-input').forEach(function (input) {
    var form = input.closest('form');
    var max = parseInt((form ? form.getAttribute('data-max') : '') || '0', 10);
    var previewBox = form ? form.querySelector('.js-avatar-preview') : null;
    input.addEventListener('change', function () {
      formError(form, '');
      var file = input.files && input.files[0];
      if (!file) return;
      if (max && file.size > max) {
        input.value = '';
        if (previewBox) previewBox.innerHTML = '';
        formError(form, '文件过大，当前最大允许 ' + humanSize(max) + '，请换一张。');
        return;
      }
      if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
        input.value = '';
        if (previewBox) previewBox.innerHTML = '';
        formError(form, '头像只支持 JPG / PNG / WebP 图片。');
        return;
      }
      if (previewBox) {
        previewBox.innerHTML = '';
        var img = document.createElement('img');
        img.className = 'avatar avatar-lg avatar-img';
        img.src = URL.createObjectURL(file);
        img.alt = '';
        previewBox.appendChild(img);
        var tip = document.createElement('span');
        tip.className = 'avatar-hint';
        tip.textContent = '新头像预览（保存后生效）';
        previewBox.appendChild(tip);
      }
    });
  });

  /* 点击图片放大查看（Lightbox） */
  var lightbox = document.getElementById('lightbox');
  if (lightbox) {
    var lightboxImg = lightbox.querySelector('img');
    var closeLightbox = function () {
      lightbox.hidden = true;
      lightboxImg.src = '';
    };
    document.querySelectorAll('[data-lightbox]').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        lightboxImg.src = link.getAttribute('href');
        lightbox.hidden = false;
      });
    });
    lightbox.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !lightbox.hidden) {
        closeLightbox();
      }
    });
  }

  /* 发布框：字数统计 + 自动增高 */
  var text = document.getElementById('composer-text');
  if (text) {
    var counter = document.getElementById('char-count');
    var update = function () {
      if (counter) {
        counter.textContent = text.value.length ? text.value.length + ' / 5000' : '';
      }
      text.style.height = 'auto';
      text.style.height = Math.min(text.scrollHeight, 320) + 'px';
    };
    text.addEventListener('input', update);
    update();
  }
})();
