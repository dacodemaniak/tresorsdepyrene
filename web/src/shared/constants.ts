/**
 * @name Constants
 * @desc Définition des constantes de l'application
 * @author IDea Factory - Jan. 2019 (dev-team@ideafactory.fr)
 * @package shared
 * @version 1.0.0
 */
export class Constants {
    public static hostname: string = window.location.hostname;

    public static get apiRoot() {
        if (Constants.hostname === 'api.tresorsdepyrene.com' || Constants.hostname === 'tresorsdepyrene.com' || Constants.hostname === 'www.tresorsdepyrene.com') {
            return 'https://api.tresorsdepyrene.com/';
        }
        return 'http://api.tresorsdepyrene.wrk/';
    }
}