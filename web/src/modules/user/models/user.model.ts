/**
 * @name UserModel
 * @desc Définition d'un utilisateur
 * @author IDea Factory - Déc. 2018 (dev-team@ideafactory.fr)
 * @package modules/user
 * @version 1.0.0
 */

export class UserModel {
    private id: number;
    private groupeId: number;
    private login: string;
    private name: string;
    private token: string;
    private addresses?: any;
    private orders?: Map<Date, any>;
    private isValid?: boolean;
    private createdAt?: any;
    private lastLogin?: any;
    private content: any;
    private menus: any;

    public constructor() {}

    public getMenus(): any {
        return this.menus;
    }

    public getId(): number {
        return this.id;
    }

    public getName(): string {
        return this.name;
    }
    public getToken(): string {
        return this.token;
    }

    public getContent(): any {
        return this.content;
    }

    public getEmail(): string {
        return this.login;
    }
    
    public hasAddresses(): boolean {
        return this.addresses ? true : false;
    }

    public getBillingAddressContent(): any {
        if (this.addresses) {
            const billing: any = this.addresses.billing;
            return billing;
        }
        return null;
    }

    public getDeliveryAddresses(): Map<string, any> {
        const deliveries: Array<any> = this.addresses.delivery;
        const asMap: Map<string, any> = new Map<string, any>();

        for(const delivery of deliveries) {
            asMap.set(delivery.name, delivery);
        }
        return asMap;
    }

    public setAddresses(addresses: any) {
        this.addresses = addresses;
    }

    public deserialize(datas: any) {
        Object.assign(this, datas);
        this.content = datas.userDetails;
        this.addresses = datas.userDetails.addresses;
    }

    
}