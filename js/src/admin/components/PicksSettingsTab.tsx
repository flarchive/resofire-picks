import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

export default class PicksSettingsTab extends Component {
  private saving: boolean = false;
  private saveResult: string | null = null;

  private picksLockOffsetMinutes: string = '0';
  private espnPollingEnabled: boolean = false;
  private espnPollIntervalMinutes: string = '5';
  private defaultWeekView: string = 'current';
  private confidenceMode: boolean = false;
  private confidencePenalty: string = 'none';

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const s = app.data.settings;
    this.picksLockOffsetMinutes  = s['resofire-picks.picks_lock_offset_minutes']  || '0';
    this.espnPollingEnabled      = s['resofire-picks.espn_polling_enabled'] === '1';
    this.espnPollIntervalMinutes = s['resofire-picks.espn_poll_interval_minutes'] || '5';
    this.defaultWeekView         = s['resofire-picks.default_week_view']          || 'current';
    this.confidenceMode          = s['resofire-picks.confidence_mode'] === '1';
    this.confidencePenalty       = s['resofire-picks.confidence_penalty']         || 'none';
  }

  private save() {
    this.saving = true;
    this.saveResult = null;
    m.redraw();

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/settings',
      body: {
        'resofire-picks.picks_lock_offset_minutes':  this.picksLockOffsetMinutes,
        'resofire-picks.espn_polling_enabled':        this.espnPollingEnabled ? '1' : '0',
        'resofire-picks.espn_poll_interval_minutes': this.espnPollIntervalMinutes,
        'resofire-picks.default_week_view':          this.defaultWeekView,
        'resofire-picks.confidence_mode':            this.confidenceMode ? '1' : '0',
        'resofire-picks.confidence_penalty':         this.confidencePenalty,
      },
    }).then(() => {
      app.data.settings['resofire-picks.picks_lock_offset_minutes']  = this.picksLockOffsetMinutes;
      app.data.settings['resofire-picks.espn_polling_enabled']        = this.espnPollingEnabled ? '1' : '0';
      app.data.settings['resofire-picks.espn_poll_interval_minutes'] = this.espnPollIntervalMinutes;
      app.data.settings['resofire-picks.default_week_view']          = this.defaultWeekView;
      app.data.settings['resofire-picks.confidence_mode']            = this.confidenceMode ? '1' : '0';
      app.data.settings['resofire-picks.confidence_penalty']         = this.confidencePenalty;
      this.saving = false;
      this.saveResult = '✅ Settings saved.';
      m.redraw();
    }).catch(() => {
      this.saving = false;
      this.saveResult = '❌ Failed to save settings.';
      m.redraw();
    });
  }

  view() {
    return (
      <div className="PicksSettingsTab">
        <div className="PicksTab-header">
          <div>
            <h3>
              <i className="fas fa-cog" />
              {' '}{app.translator.trans('resofire-picks.admin.nav.settings')}
            </h3>
          </div>
        </div>

        {/* Pick Locking */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.settings.locking_title')}
          </h4>

          <div className="Form-group">
            <label>{app.translator.trans('resofire-picks.admin.settings.lock_offset')}</label>
            <div className="PicksInputRow">
              <input
                className="FormControl PicksInputRow-input"
                type="number"
                min="0"
                max="120"
                value={this.picksLockOffsetMinutes}
                oninput={(e: InputEvent) => { this.picksLockOffsetMinutes = (e.target as HTMLInputElement).value; }}
              />
              <span className="PicksInputRow-label">
                {app.translator.trans('resofire-picks.admin.settings.minutes_before_kickoff')}
              </span>
            </div>
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.settings.lock_offset_help')}
            </p>
          </div>
        </div>

        {/* Default Week View */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.settings.display_title')}
          </h4>

          <div className="Form-group">
            <label>{app.translator.trans('resofire-picks.admin.settings.default_week_view')}</label>
            <select
              className="FormControl"
              value={this.defaultWeekView}
              onchange={(e: Event) => { this.defaultWeekView = (e.target as HTMLSelectElement).value; }}
            >
              <option value="current">{app.translator.trans('resofire-picks.admin.settings.week_view_current')}</option>
              <option value="first">{app.translator.trans('resofire-picks.admin.settings.week_view_first')}</option>
            </select>
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.settings.default_week_view_help')}
            </p>
          </div>
        </div>

        {/* Confidence Mode */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.settings.confidence_title')}
          </h4>

          <div className="Form-group">
            <label className="checkbox">
              <input
                type="checkbox"
                checked={this.confidenceMode}
                onchange={(e: Event) => { this.confidenceMode = (e.target as HTMLInputElement).checked; }}
              />
              {' '}{app.translator.trans('resofire-picks.admin.settings.confidence_enabled')}
            </label>
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.settings.confidence_help')}
            </p>
          </div>

          {this.confidenceMode && (
            <div className="Form-group">
              <label>{app.translator.trans('resofire-picks.admin.settings.confidence_penalty')}</label>
              <select
                className="FormControl"
                value={this.confidencePenalty}
                onchange={(e: Event) => { this.confidencePenalty = (e.target as HTMLSelectElement).value; }}
              >
                <option value="none">{app.translator.trans('resofire-picks.admin.settings.penalty_none')}</option>
                <option value="half">{app.translator.trans('resofire-picks.admin.settings.penalty_half')}</option>
                <option value="full">{app.translator.trans('resofire-picks.admin.settings.penalty_full')}</option>
              </select>
              <p className="helpText">
                {this.confidencePenalty === 'none' && app.translator.trans('resofire-picks.admin.settings.penalty_none_help')}
                {this.confidencePenalty === 'half' && app.translator.trans('resofire-picks.admin.settings.penalty_half_help')}
                {this.confidencePenalty === 'full' && app.translator.trans('resofire-picks.admin.settings.penalty_full_help')}
              </p>
            </div>
          )}
        </div>

        {/* ESPN Live Polling */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.settings.polling_title')}
          </h4>

          <div className="Form-group">
            <label className="checkbox">
              <input
                type="checkbox"
                checked={this.espnPollingEnabled}
                onchange={(e: Event) => { this.espnPollingEnabled = (e.target as HTMLInputElement).checked; }}
              />
              {' '}{app.translator.trans('resofire-picks.admin.settings.espn_polling_enabled')}
            </label>
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.settings.espn_polling_help')}
            </p>
          </div>

          {this.espnPollingEnabled && (
            <div className="Form-group">
              <label>{app.translator.trans('resofire-picks.admin.settings.poll_interval')}</label>
              <div className="PicksInputRow">
                <input
                  className="FormControl PicksInputRow-input"
                  type="number"
                  min="1"
                  max="60"
                  value={this.espnPollIntervalMinutes}
                  oninput={(e: InputEvent) => { this.espnPollIntervalMinutes = (e.target as HTMLInputElement).value; }}
                />
                <span className="PicksInputRow-label">
                  {app.translator.trans('resofire-picks.admin.settings.minutes')}
                </span>
              </div>
              <p className="helpText">
                {app.translator.trans('resofire-picks.admin.settings.poll_interval_help')}
              </p>
            </div>
          )}

          <div className="Form-group">
            <p className="helpText PicksHelpNote">
              <i className="fas fa-info-circle" />
              {' '}{app.translator.trans('resofire-picks.admin.settings.polling_note')}
            </p>
          </div>
        </div>

        {this.saveResult && (
          <div className="PicksAlert PicksAlert--info">{this.saveResult}</div>
        )}

        <div className="Form-group">
          <Button
            className="Button Button--primary"
            loading={this.saving}
            onclick={() => this.save()}
          >
            {app.translator.trans('resofire-picks.admin.common.save')}
          </Button>
        </div>
      </div>
    );
  }
}
