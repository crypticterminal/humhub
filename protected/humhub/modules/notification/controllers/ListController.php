<?php

/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2015 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\notification\controllers;

use humhub\components\Controller;
use humhub\modules\notification\models\Notification;
use Yii;
use yii\db\IntegrityException;

/**
 * ListController
 *
 * @since 0.5
 */
class ListController extends Controller
{

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'acl' => [
                'class' => \humhub\components\behaviors\AccessControl::class,
            ]
        ];
    }

    /**
     * Returns a List of all notifications for an user
     */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        $notifications = Notification::loadMore(Yii::$app->request->get('from', 0));
        $lastEntryId = 0;

        $output = "";
        foreach ($notifications as $notification) {
            try {
                $output .= $notification->getBaseModel()->render();
                $lastEntryId = $notification->id;
                $notification->desktop_notified = 1;
                $notification->update();
            } catch (\Exception $e) {
                Yii::error('Could not display notification: ' . $notification->id . '(' . $e . ')');
            }
        }

        return [
            'newNotifications' => Notification::findUnseen()->count(),
            'lastEntryId' => $lastEntryId,
            'output' => $output,
            'counter' => count($notifications)
        ];
    }

    /**
     * Marks all notifications as seen
     */
    public function actionMarkAsSeen()
    {
        $this->forcePostRequest();

        Yii::$app->response->format = 'json';
        $count = Notification::updateAll(['seen' => 1], ['user_id' => Yii::$app->user->id]);

        return [
            'success' => true,
            'count' => $count
        ];
    }

    /**
     * Returns new notifications
     *
     * @deprecated since version 1.2
     */
    public function actionGetUpdateJson()
    {
        Yii::$app->response->format = 'json';

        return $this->getUpdates();
    }

    /**
     * Returns a JSON which contains
     * - Number of new / unread notification
     * - Notification Output for new HTML5 Notifications
     *
     * @return string JSON String
     */
    public static function getUpdates($includeContent = true)
    {
        $update['newNotifications'] = Notification::findUnseen()->count();

        $unnotified = Notification::findUnnotifiedInFrontend()->all();

        $update['notifications'] = [];
        foreach ($unnotified as $notification) {

            if ($includeContent && Yii::$app->getModule('notification')->settings->user()->getInherit('enable_html5_desktop_notifications', true)) {
                try {
                    $update['notifications'][] = $notification->getBaseModel()->text();
                } catch (IntegrityException $ex) {
                    $notification->delete();
                    Yii::warning('Deleted inconsistent notification with id ' . $notification->id . '. ' . $ex->getMessage());
                    continue;
                }
            }
            $notification->desktop_notified = 1;
            $notification->update();
        }

        return $update;
    }

}
