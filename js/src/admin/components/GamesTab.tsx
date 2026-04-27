import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import PickEvent from '../../common/models/PickEvent';
import Team from '../../common/models/Team';
import Week from '../../common/models/Week';
import Season from '../../common/models/Season';
import ResultModal from './ResultModal';

export default class GamesTab extends Component {
  private events: PickEvent[] = [];
  private loading: boolean = false;
  private filterWeekId: string = 'all';
  private filterStatus: string = 'all';
  private search: string = '';
  private offset: number = 0;
  private limit: number = 50;
  private hasMore: boolean = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);

    // Pre-select the first week if weeks are available
    const weeks = app.store.all<Week>('picks-weeks');
    if (weeks.length > 0) {
      this.filterWeekId = String(weeks[0].id());
    }

    this.loadEvents(true);
  }

  private loadEvents(reset: boolean = false) {
    if (reset) {
      this.offset = 0;
      this.events = [];
    }

    this.loading = true;
    m.redraw();

    app.store
      .find<PickEvent[]>('picks-events', {
        include: 'homeTeam,awayTeam,week',
        page: { limit: this.limit, offset: this.offset },
      })
      .then((results) => {
        const incoming = Array.isArray(results) ? results : [];
        if (reset) {
          this.events = incoming;
        } else {
          this.events = [...this.events, ...incoming];
        }
        this.hasMore = incoming.length >= this.limit;
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  private loadMore() {
    this.offset += this.limit;
    this.loadEvents(false);
  }

  private filteredEvents(): PickEvent[] {
    return this.events.filter((event) => {
      if (this.filterWeekId !== 'all') {
        if (String(event.weekId()) !== this.filterWeekId) return false;
      }

      if (this.filterStatus !== 'all') {
        if (event.status() !== this.filterStatus) return false;
      }

      if (this.search) {
        const q = this.search.toLowerCase();
        const home = event.homeTeam();
        const away = event.awayTeam();
        const homeName = (home ? (home as Team).name() : '').toLowerCase();
        const awayName = (away ? (away as Team).name() : '').toLowerCase();
        if (!homeName.includes(q) && !awayName.includes(q)) return false;
      }

      return true;
    });
  }

  private statusBadge(status: string): Mithril.Children {
    const classes: Record<string, string> = {
      scheduled: 'PicksBadge--scheduled',
      closed:    'PicksBadge--closed',
      finished:  'PicksBadge--finished',
    };
    return (
      <span className={`PicksBadge ${classes[status] || ''}`}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </span>
    );
  }

  private renderTeamLogo(team: Team | false): Mithril.Children {
    if (!team) return <div className="PicksTeamLogo PicksTeamLogo--placeholder">?</div>;

    const logoUrl = team.logoUrl();
    if (logoUrl) {
      return <img src={logoUrl} alt={team.name() || ''} className="PicksTeamLogo PicksTeamLogo--small" />;
    }

    return (
      <div className="PicksTeamLogo PicksTeamLogo--placeholder PicksTeamLogo--small">
        {(team.abbreviation() || team.name() || '?').charAt(0)}
      </div>
    );
  }

  private formatDate(dateStr: string | null): string {
    if (!dateStr) return '—';
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short', day: 'numeric', year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  }

  view() {
    const weeks   = app.store.all<Week>('picks-weeks').sort((a, b) => {
      if (a.seasonType() !== b.seasonType()) return a.seasonType() === 'regular' ? -1 : 1;
      return (a.weekNumber() || 0) - (b.weekNumber() || 0);
    });
    const filtered = this.filteredEvents();

    return (
      <div className="PicksGamesTab">
        <div className="PicksTab-header">
          <div>
            <h3>
              <i className="fas fa-football" />
              {' '}{app.translator.trans('resofire-picks.admin.nav.games')}
            </h3>
            <p className="PicksTab-meta">
              {this.events.length} {app.translator.trans('resofire-picks.admin.games.total_label')}
            </p>
          </div>
        </div>

        <div className="PicksTab-filters">
          <select
            className="FormControl"
            value={this.filterWeekId}
            onchange={(e: Event) => {
              this.filterWeekId = (e.target as HTMLSelectElement).value;
            }}
          >
            <option value="all">{app.translator.trans('resofire-picks.admin.games.all_weeks')}</option>
            {weeks.map(w => (
              <option key={String(w.id())} value={String(w.id())}>
                {w.name()}
              </option>
            ))}
          </select>

          <select
            className="FormControl"
            value={this.filterStatus}
            onchange={(e: Event) => {
              this.filterStatus = (e.target as HTMLSelectElement).value;
            }}
          >
            <option value="all">{app.translator.trans('resofire-picks.admin.games.all_statuses')}</option>
            <option value="scheduled">{app.translator.trans('resofire-picks.lib.status.scheduled')}</option>
            <option value="closed">{app.translator.trans('resofire-picks.lib.status.closed')}</option>
            <option value="finished">{app.translator.trans('resofire-picks.lib.status.finished')}</option>
          </select>

          <input
            className="FormControl"
            type="text"
            placeholder={app.translator.trans('resofire-picks.admin.games.search_placeholder')}
            value={this.search}
            oninput={(e: InputEvent) => { this.search = (e.target as HTMLInputElement).value; }}
          />
        </div>

        {this.loading && this.events.length === 0 ? (
          <LoadingIndicator />
        ) : filtered.length === 0 ? (
          <div className="PicksEmptyState">
            {app.translator.trans('resofire-picks.admin.games.no_games')}
          </div>
        ) : (
          <>
            <div className="PicksCardList">
              <div className="PicksCardList-header PicksCardList-header--games">
                <div>{app.translator.trans('resofire-picks.admin.games.col_home')}</div>
                <div>{app.translator.trans('resofire-picks.admin.games.col_away')}</div>
                <div>{app.translator.trans('resofire-picks.admin.games.col_date')}</div>
                <div>{app.translator.trans('resofire-picks.admin.games.col_status')}</div>
                <div>{app.translator.trans('resofire-picks.admin.games.col_score')}</div>
                <div></div>
              </div>

              {filtered.map((event) => {
                const homeTeam = event.homeTeam() as Team | false;
                const awayTeam = event.awayTeam() as Team | false;

                return (
                  <div key={String(event.id())} className="PicksCardList-row PicksCardList-row--games">
                    <div className="PicksCardList-cell PicksTeamCell">
                      {this.renderTeamLogo(homeTeam)}
                      <span>{homeTeam ? homeTeam.name() : '—'}</span>
                    </div>

                    <div className="PicksCardList-cell PicksTeamCell">
                      {this.renderTeamLogo(awayTeam)}
                      <span>{awayTeam ? awayTeam.name() : '—'}</span>
                    </div>

                    <div className="PicksCardList-cell PicksCardList-cell--muted">
                      {this.formatDate(event.matchDate())}
                    </div>

                    <div className="PicksCardList-cell">
                      {this.statusBadge(event.status() || 'scheduled')}
                    </div>

                    <div className="PicksCardList-cell">
                      {event.homeScore() !== null && event.awayScore() !== null
                        ? `${event.homeScore()} – ${event.awayScore()}`
                        : '—'}
                    </div>

                    <div className="PicksCardList-cell PicksCardList-cell--actions">
                      <Button
                        className="Button Button--primary Button--icon"
                        icon="fas fa-check"
                        title={app.translator.trans('resofire-picks.admin.games.enter_result')}
                        onclick={() =>
                          app.modal.show(ResultModal, {
                            event,
                            onsave: () => this.loadEvents(true),
                          })
                        }
                      />
                    </div>
                  </div>
                );
              })}
            </div>

            {this.loading && <LoadingIndicator />}

            {!this.loading && this.hasMore && (
              <div className="PicksLoadMore">
                <Button
                  className="Button"
                  onclick={() => this.loadMore()}
                >
                  {app.translator.trans('resofire-picks.admin.games.load_more')}
                </Button>
              </div>
            )}
          </>
        )}
      </div>
    );
  }
}
