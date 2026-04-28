import Extend from 'flarum/common/extenders';
import PicksPage from './components/PicksPage';

export default [
  new Extend.Routes()
    .add('picks', '/picks', PicksPage)
    .add('picks.week', '/picks/week/:weekId', PicksPage),
];
