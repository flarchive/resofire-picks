import app from 'flarum/admin/app';
import AdminPage, { AdminHeaderAttrs } from 'flarum/admin/components/AdminPage';
import type { IPageAttrs } from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import TeamsTab from './TeamsTab';

export default class PicksPage extends AdminPage {
  private activeTab: string = 'teams';

  oninit(vnode: Mithril.Vnode<IPageAttrs, this>) {
    super.oninit(vnode);

    // Restore tab from URL query param if present.
    const param = m.route.param('tab');
    const validTabs = ['dashboard', 'sync', 'seasons', 'teams', 'games', 'scores', 'settings'];
    if (param && validTabs.includes(param)) {
      this.activeTab = param;
    }
  }

  headerInfo(): AdminHeaderAttrs {
    return {
      className: 'PicksAdminPage',
      icon: 'fas fa-football',
      title: app.translator.trans('resofire-picks.admin.nav.picks'),
      description: app.translator.trans('resofire-picks.admin.page.description'),
    };
  }

  content(): Mithril.Children {
    return (
      <div className="PicksAdminPage-body">
        <div className="PicksAdminPage-tabs">
          {this.renderTab('teams',    'fas fa-users',        'resofire-picks.admin.nav.teams')}
          {this.renderTab('sync',     'fas fa-sync',         'resofire-picks.admin.nav.sync')}
          {this.renderTab('seasons',  'fas fa-calendar-alt', 'resofire-picks.admin.nav.seasons')}
          {this.renderTab('games',    'fas fa-football',     'resofire-picks.admin.nav.games')}
          {this.renderTab('scores',   'fas fa-trophy',       'resofire-picks.admin.nav.scores')}
          {this.renderTab('settings', 'fas fa-cog',          'resofire-picks.admin.nav.settings')}
        </div>

        <div className="PicksAdminPage-content">
          {this.renderActiveTab()}
        </div>
      </div>
    );
  }

  private renderTab(key: string, icon: string, translationKey: string): Mithril.Children {
    const isActive = this.activeTab === key;

    return (
      <button
        className={`Button PicksAdminPage-tab ${isActive ? 'Button--primary' : ''}`}
        onclick={() => {
          this.activeTab = key;
          const base = m.route.get().split('?')[0];
          m.route.set(base, { tab: key }, { replace: true });
        }}
      >
        <i className={icon} />
        {' '}
        {app.translator.trans(translationKey)}
      </button>
    );
  }

  private renderActiveTab(): Mithril.Children {
    switch (this.activeTab) {
      case 'teams':
        return <TeamsTab />;
      case 'sync':
        // Built in Slice 3 (schedule sync) and Slice 8 (score sync).
        return (
          <div className="PicksPlaceholder">
            {app.translator.trans('resofire-picks.admin.placeholder.coming_soon')}
          </div>
        );
      case 'seasons':
        // Built in Slice 3.
        return (
          <div className="PicksPlaceholder">
            {app.translator.trans('resofire-picks.admin.placeholder.coming_soon')}
          </div>
        );
      case 'games':
        // Built in Slice 4.
        return (
          <div className="PicksPlaceholder">
            {app.translator.trans('resofire-picks.admin.placeholder.coming_soon')}
          </div>
        );
      case 'scores':
        // Built in Slice 7.
        return (
          <div className="PicksPlaceholder">
            {app.translator.trans('resofire-picks.admin.placeholder.coming_soon')}
          </div>
        );
      case 'settings':
        // Built in Slice 9.
        return (
          <div className="PicksPlaceholder">
            {app.translator.trans('resofire-picks.admin.placeholder.coming_soon')}
          </div>
        );
      default:
        return <TeamsTab />;
    }
  }
}
