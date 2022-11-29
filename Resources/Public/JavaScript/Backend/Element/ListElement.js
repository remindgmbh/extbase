var __decorate = this && this.__decorate || function (t, e, l, a) {
  var n, i = arguments.length, o = i < 3 ? e : null === a ? a = Object.getOwnPropertyDescriptor(e, l) : a;
  if ("object" == typeof Reflect && "function" == typeof Reflect.decorate) o = Reflect.decorate(t, e, l, a);
  else for (var s = t.length - 1; s >= 0; s--)(n = t[s]) && (o = (i < 3 ? n(o) : i > 3 ? n(e, l, o) : n(e, l)) || o);
  return i > 3 && o && Object.defineProperty(e, l, o), o
};
define(
  ["require", "exports", "lit", "lit/decorators", "TYPO3/CMS/Core/lit-helper", "TYPO3/CMS/Backend/Severity", "TYPO3/CMS/Backend/Modal", "TYPO3/CMS/Backend/Enum/Severity", "TYPO3/CMS/Backend/Element/IconElement"],
  (function (_t, e, l, a, n) {
    "use strict"; Object.defineProperty(e, "__esModule", { value: !0 }), e.ListElement = void 0; let r = class extends l.LitElement {
      constructor() {
        super(),
          this.type = "textarea",
          this.selectorData = "",
          this.l10n = {},
          this.table = [],
          this.selectorData = this.getAttribute("selector"),
          this.readTableFromTextarea()
      }
      get firstRow() {
        return this.table[0] || []
      }
      createRenderRoot() {
        return this
      }
      render() {
        return this.renderTemplate()
      }
      provideMinimalTable() {
        0 !== this.table.length && 0 !== this.firstRow.length || (this.table = [[""]])
      }
      readTableFromTextarea() {
        let t = document.querySelector(this.selectorData), e = [];
        t.value.split("\n").forEach(t => {
          if ("" !== t) {
            e.push([t])
          }
        }), this.table = e
      }
      writeTableSyntaxToTextarea() {
        let t = document.querySelector(this.selectorData), e = "";
        this.table.forEach(t => {
          e += t.reduce((t, e) => {
            return t + e
          }, "") + "\n"
        }), t.value = e, t.dispatchEvent(new CustomEvent("change", { bubbles: !0 }))
      }
      modifyTable(t, e, l) {
        const a = t.target; this.table[e][l] = a.value, this.writeTableSyntaxToTextarea(), this.requestUpdate()
      }
      moveRow(t, e, l) {
        const a = this.table.splice(e, 1); this.table.splice(l, 0, ...a), this.writeTableSyntaxToTextarea(), this.requestUpdate()
      }
      appendRow(t, e) {
        let l = this.firstRow.concat().fill(""), a = new Array(1).fill(l); this.table.splice(e + 1, 0, ...a), this.writeTableSyntaxToTextarea(), this.requestUpdate()
      }
      removeRow(t, e) {
        this.table.splice(e, 1), this.writeTableSyntaxToTextarea(), this.requestUpdate()
      }
      renderTemplate() {
        this.provideMinimalTable(); const t = Object.keys(this.firstRow).map(t => parseInt(t, 10)), e = t[t.length - 1], a = this.table.length - 1;
        return l.html`
        <style>
          :host, typo3-backend-list { display: inline-block; }
        </style>
        <div>
            ${this.table.map((t, e) => l.html`
              <div style="display: flex; align-items: center; gap: 5px; padding: 0.5rem 0;">
                ${t.map((t, a) => l.html`
                <div style="flex-grow: 1;">${this.renderDataElement(t, e, a)}</div>
                `)}
                <div>${this.renderRowButtons(e, a)}</div>
              </div>
            `)}
        </div>
      `
      }
      renderDataElement(t, e, a) {
        const n = t => this.modifyTable(t, e, a); switch (this.type) {
          case "input": return l.html`
          <input class="form-control" type="text" name="TABLE[c][${e}][${a}]"
            @change="${n}" .value="${t.replace(/\n/g, "<br>")}">
        `; case "textarea": default: return l.html`
          <textarea class="form-control" rows="6" name="TABLE[c][${e}][${a}]"
            @change="${n}" .value="${t.replace(/<br[ ]*\/?>/g, "\n")}"></textarea>
        `}
      }
      renderRowButtons(t, e) {
        const a = { title: 0 === t ? (0, n.lll)("table_bottom") : (0, n.lll)("table_up"), class: 0 === t ? "bar-down" : "up", target: 0 === t ? e : t - 1 }, i = { title: t === e ? (0, n.lll)("table_top") : (0, n.lll)("table_down"), class: t === e ? "bar-up" : "down", target: t === e ? 0 : t + 1 };
        return l.html`
        <span class="btn-group${"input" === this.type ? "" : "-vertical"}">
          <button class="btn btn-default" type="button" title="${a.title}"
                  @click="${e => this.moveRow(e, t, a.target)}">
            <typo3-backend-icon identifier="actions-chevron-${a.class}" size="small"></typo3-backend-icon>
          </button>
          <button class="btn btn-default" type="button" title="${i.title}"
                  @click="${e => this.moveRow(e, t, i.target)}">
            <typo3-backend-icon identifier="actions-chevron-${i.class}" size="small"></typo3-backend-icon>
          </button>
          <button class="btn btn-default" type="button" title="${(0, n.lll)("table_removeRow")}"
                  @click="${e => this.removeRow(e, t)}">
            <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
          </button>
          <button class="btn btn-default" type="button" title="${(0, n.lll)("table_addRow")}"
                  @click="${e => this.appendRow(e, t)}">
            <typo3-backend-icon identifier="actions-add" size="small"></typo3-backend-icon>
          </button>
        </span>
      `
      }
    };
    __decorate([(0, a.property)({ type: String })], r.prototype, "type", void 0),
      __decorate([(0, a.property)({ type: String })], r.prototype, "selectorData", void 0),
      __decorate([(0, a.property)({ type: Object })], r.prototype, "l10n", void 0),
      r = __decorate([(0, a.customElement)("typo3-backend-list")], r), e.ListElement = r
  }));