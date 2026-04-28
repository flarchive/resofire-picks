import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

export default class SyncSettingsTab extends Component {
  private saving: boolean = false;
  private saveResult: string | null = null;

  // Local copies of settings for editing
  private cfbdApiKey: string = '';
  private seasonYear: string = '';
  private conferenceFilter: string = '';
  private syncRegularSeason: boolean = true;
  private syncPostseason: boolean = true;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const s = app.data.settings;
    this.cfbdApiKey        = s['resofire-picks.cfbd_api_key']      || '';
    this.seasonYear        = s['resofire-picks.season_year']       || String(new Date().getFullYear());
    this.conferenceFilter  = s['resofire-picks.conference_filter'] || '';
    this.syncRegularSeason = s['resofire-picks.sync_regular_season'] !== '0';
    this.syncPostseason    = s['resofire-picks.sync_postseason'] !== '0';
  }

  private save() {
    this.saving = true;
    this.saveResult = null;
    m.redraw();

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/settings',
      body: {
        'resofire-picks.cfbd_api_key':       this.cfbdApiKey,
        'resofire-picks.season_year':        this.seasonYear,
        'resofire-picks.conference_filter':  this.conferenceFilter,
        'resofire-picks.sync_regular_season': this.syncRegularSeason ? '1' : '0',
        'resofire-picks.sync_postseason':    this.syncPostseason ? '1' : '0',
      },
    }).then(() => {
      // Update in-memory settings
      app.data.settings['resofire-picks.cfbd_api_key']        = this.cfbdApiKey;
      app.data.settings['resofire-picks.season_year']         = this.seasonYear;
      app.data.settings['resofire-picks.conference_filter']   = this.conferenceFilter;
      app.data.settings['resofire-picks.sync_regular_season'] = this.syncRegularSeason ? '1' : '0';
      app.data.settings['resofire-picks.sync_postseason']     = this.syncPostseason ? '1' : '0';
      this.saving = false;
      this.saveResult = '✅ Settings saved.';
      m.redraw();
    }).catch(() => {
      this.saving = false;
      this.saveResult = '❌ Failed to save settings.';
      m.redraw();
    });
  }

  private formatDate(isoString: string | null): string {
    if (!isoString) return 'Never';
    try {
      return new Date(isoString).toLocaleString();
    } catch {
      return isoString;
    }
  }

  view() {
    const s = app.data.settings;
    const lastTeams    = s['resofire-picks.last_teams_sync']    || null;
    const lastSchedule = s['resofire-picks.last_schedule_sync'] || null;
    const lastScores   = s['resofire-picks.last_scores_sync']   || null;

    return (
      <div className="PicksSyncSettingsTab">
        <div className="PicksTab-header">
          <div>
            <h3>
              <i className="fas fa-sync" />
              {' '}{app.translator.trans('resofire-picks.admin.nav.sync')}
            </h3>
          </div>
        </div>

        {/* Sync Status */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.sync.status_title')}
          </h4>
          <div className="PicksSyncStatus">
            <div className="PicksSyncStatus-row">
              <i className="fas fa-users" />
              <span>{app.translator.trans('resofire-picks.admin.sync.last_teams')}</span>
              <strong>{this.formatDate(lastTeams)}</strong>
            </div>
            <div className="PicksSyncStatus-row">
              <i className="fas fa-calendar-alt" />
              <span>{app.translator.trans('resofire-picks.admin.sync.last_schedule')}</span>
              <strong>{this.formatDate(lastSchedule)}</strong>
            </div>
            <div className="PicksSyncStatus-row">
              <i className="fas fa-trophy" />
              <span>{app.translator.trans('resofire-picks.admin.sync.last_scores')}</span>
              <strong>{this.formatDate(lastScores)}</strong>
            </div>
          </div>
        </div>

        {/* API Configuration */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.sync.api_title')}
          </h4>

          <div className="Form-group">
            <label>{app.translator.trans('resofire-picks.admin.sync.cfbd_api_key')}</label>
            <input
              className="FormControl"
              type="password"
              value={this.cfbdApiKey}
              placeholder="Your CFBD API key"
              oninput={(e: InputEvent) => { this.cfbdApiKey = (e.target as HTMLInputElement).value; }}
            />
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.sync.cfbd_api_key_help')}
            </p>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('resofire-picks.admin.sync.season_year')}</label>
            <input
              className="FormControl"
              type="number"
              value={this.seasonYear}
              min="2000"
              max="2099"
              oninput={(e: InputEvent) => { this.seasonYear = (e.target as HTMLInputElement).value; }}
            />
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.sync.season_year_help')}
            </p>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('resofire-picks.admin.sync.conference_filter')}</label>
            <input
              className="FormControl"
              type="text"
              value={this.conferenceFilter}
              placeholder="e.g. SEC (leave blank for all FBS)"
              oninput={(e: InputEvent) => { this.conferenceFilter = (e.target as HTMLInputElement).value; }}
            />
            <p className="helpText">
              {app.translator.trans('resofire-picks.admin.sync.conference_filter_help')}
            </p>
          </div>
        </div>

        {/* Sync Options */}
        <div className="PicksSettingsSection">
          <h4 className="PicksSettingsSection-title">
            {app.translator.trans('resofire-picks.admin.sync.options_title')}
          </h4>

          <div className="Form-group">
            <label className="checkbox">
              <input
                type="checkbox"
                checked={this.syncRegularSeason}
                onchange={(e: Event) => { this.syncRegularSeason = (e.target as HTMLInputElement).checked; }}
              />
              {' '}{app.translator.trans('resofire-picks.admin.sync.sync_regular_season')}
            </label>
          </div>

          <div className="Form-group">
            <label className="checkbox">
              <input
                type="checkbox"
                checked={this.syncPostseason}
                onchange={(e: Event) => { this.syncPostseason = (e.target as HTMLInputElement).checked; }}
              />
              {' '}{app.translator.trans('resofire-picks.admin.sync.sync_postseason')}
            </label>
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
