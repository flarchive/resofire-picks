import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';

export { default as extend } from './extend';

app.initializers.add('resofire/picks', () => {
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('picksCanView') && !app.session.user?.isAdmin()) return;

    items.add(
      'picks',
      <LinkButton href={app.route('picks')} icon="fas fa-football">
        {app.forum.attribute('picksNavLabel') || app.translator.trans('resofire-picks.lib.nav.picks')}
      </LinkButton>,
      80
    );
  });
});
